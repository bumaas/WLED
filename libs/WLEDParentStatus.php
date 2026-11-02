<?php /** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

/**
 * Eigener Status folgt dem direkten Parent, Hop für Hop (Vertrag nach dem eingebauten MQTT Client):
 *   - kein Parent: IS_INACTIVE (104)
 *   - Parent nicht aktiv: STATUS_PARENT_INACTIVE (201), steht in der form.json
 *   - Parent aktiv: onParentActive() (nur bei KR_READY), dann IS_ACTIVE (102)
 *
 * IM_CHANGESTATUS wird nur auf dem direkten Parent registriert, FM_CONNECT/FM_DISCONNECT auf der
 * eigenen Instanz. Da der Kernel IM_CHANGESTATUS nur von der Instanz schickt, die sich selbst
 * ändert, wandert ein Ausfall des WebSocket Clients so über den Splitter bis zu Master und Segment.
 *
 * Einbinden: Create() setzt IS_INACTIVE, ApplyChanges() ruft watchParent(), MessageSink() reicht
 * an handleParentMessage() weiter, und bei KR_READY läuft updateStatusFromParent().
 */
trait WLEDParentStatusTrait
{
    private const string BUFFER_PARENT_ID       = 'ParentID';
    private const int    STATUS_PARENT_INACTIVE = 201;

    /** Was zu tun ist, wenn der Parent aktiv ist und der Kernel bereit (z. B. Zustand anfordern). */
    abstract private function onParentActive(): void;

    /** Registrierungen auf den aktuellen Parent nachziehen. */
    private function watchParent(): void
    {
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        $alt = (int)$this->GetBuffer(self::BUFFER_PARENT_ID);
        $neu = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($alt === $neu) {
            return;
        }
        if ($alt > 0) {
            $this->UnregisterMessage($alt, IM_CHANGESTATUS);
        }
        if ($neu > 0) {
            $this->RegisterMessage($neu, IM_CHANGESTATUS);
        }
        $this->SetBuffer(self::BUFFER_PARENT_ID, (string)$neu);
    }

    /** Eigenen Status aus dem Status des direkten Parents ableiten. */
    private function updateStatusFromParent(): void
    {
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parentID === 0) {
            $this->setStatusIfChanged(IS_INACTIVE);
            return;
        }
        if (IPS_GetInstance($parentID)['InstanceStatus'] !== IS_ACTIVE) {
            $this->setStatusIfChanged(self::STATUS_PARENT_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->onParentActive();
        }
        $this->setStatusIfChanged(IS_ACTIVE);
    }

    /** Im MessageSink aufrufen; true, wenn die Nachricht den Parent betraf. */
    private function handleParentMessage(int $SenderID, int $Message): bool
    {
        if ($SenderID === $this->InstanceID && ($Message === FM_CONNECT || $Message === FM_DISCONNECT)) {
            $this->watchParent();
            $this->updateStatusFromParent();
            return true;
        }
        if ($Message === IM_CHANGESTATUS && $SenderID === (int)$this->GetBuffer(self::BUFFER_PARENT_ID)) {
            $this->updateStatusFromParent();
            return true;
        }
        return false;
    }

    private function setStatusIfChanged(int $status): void
    {
        if (IPS_GetInstance($this->InstanceID)['InstanceStatus'] !== $status) {
            $this->SetStatus($status);
        }
    }
}
