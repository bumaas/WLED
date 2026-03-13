<?php /** @noinspection PhpUnused */

require_once __DIR__ . '/../libs/WLEDIds.php';
require_once __DIR__ . '/../libs/ModuleDebug.php';

use libs\WLEDIds;

/** @noinspection AutoloadingIssuesInspection */
class WLEDDiscovery extends IPSModuleStrict
{
    use ModuleDebugTrait;

    private const string DISCOVERY_SEARCHTARGET = '_wled._tcp';
    private const string BUFFER_DEVICES         = 'Devices';
    private const string BUFFER_SEARCHACTIVE    = 'SearchActive';
    private const string TIMER_LOAD             = 'DiscoveryTimer';

    public function Create(): void
    {
        parent::Create();
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterTimer(self::TIMER_LOAD, 0, 'IPS_RequestAction($_IPS["TARGET"], "discover", "");');
        $this->RegisterPropertyBoolean('EnableExpertDebug', false);

        $this->SetBuffer(self::BUFFER_DEVICES, json_encode([], JSON_THROW_ON_ERROR));
        $this->SetBuffer(self::BUFFER_SEARCHACTIVE, json_encode(false, JSON_THROW_ON_ERROR));
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->debugExpert(__FUNCTION__, 'Module apply changes');
        $this->SetBuffer(self::BUFFER_SEARCHACTIVE, json_encode(false, JSON_THROW_ON_ERROR));
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            $this->debugExpert(__FUNCTION__, 'Kernel ready received');
            $this->ApplyChanges();
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        $this->debugExpert(__FUNCTION__, 'Action requested', ['ident' => $Ident]);
        if ($Ident !== 'discover') {
            return;
        }

        $this->SetTimerInterval(self::TIMER_LOAD, 0);
        try {
            $this->performDiscovery();
        } catch (Throwable $e) {
            $this->debugExpert(__FUNCTION__, 'Discovery failed', ['error' => $e->getMessage()], true);
            $this->stopSearch();
        }
    }

    public function GetConfigurationForm(): string
    {
        $searchActive = json_decode($this->GetBuffer(self::BUFFER_SEARCHACTIVE), false, 512, JSON_THROW_ON_ERROR);
        if (!$searchActive) {
            $this->debugExpert(__FUNCTION__, 'Start auto discovery timer');
            $this->SetBuffer(self::BUFFER_SEARCHACTIVE, json_encode(true, JSON_THROW_ON_ERROR));
            // Timer-basiert, damit die Form ohne blockierenden Netzwerkscan schnell geöffnet wird.
            $this->SetTimerInterval(self::TIMER_LOAD, 1000);
        }

        $elements = $this->formElements();
        $actions  = $this->formActions();
        $status   = [];

        return json_encode(compact('elements', 'actions', 'status'), JSON_THROW_ON_ERROR);
    }

    private function performDiscovery(): void
    {
        $this->debugExpert(__FUNCTION__, 'Starting discovery');
        $existingSplitters = IPS_GetInstanceListByModuleID(WLEDIds::MODULE_WLED_SPLITTER);
        $foundDevices      = $this->scanNetworkForDevices();
        $formValues        = $this->mapDevicesToForm($foundDevices, $existingSplitters);

        $jsonValues = json_encode($formValues, JSON_THROW_ON_ERROR);
        $this->debugExpert(__FUNCTION__, 'Discovery finished', ['devices' => count($formValues)]);
        $this->SetBuffer(self::BUFFER_DEVICES, $jsonValues);
        $this->UpdateFormField('configurator', 'values', $jsonValues);
        $this->stopSearch();
    }

    private function stopSearch(): void
    {
        $this->SetBuffer(self::BUFFER_SEARCHACTIVE, json_encode(false, JSON_THROW_ON_ERROR));
        $this->UpdateFormField('searchingInfo', 'visible', false);
    }

    private function scanNetworkForDevices(): array
    {
        $mdnsID = $this->getMdnsInstance();
        $this->debugExpert(__FUNCTION__, 'mDNS browse started', ['service' => self::DISCOVERY_SEARCHTARGET]);

        ZC_QueryServiceType($mdnsID, self::DISCOVERY_SEARCHTARGET, '');
        IPS_Sleep(2000);
        $services = ZC_QueryServiceType($mdnsID, self::DISCOVERY_SEARCHTARGET, '');
        $this->debugExpert(__FUNCTION__, 'mDNS browse finished', ['services' => count($services)]);

        $devices = [];
        foreach ($services as $service) {
            if (empty($service['IPv4'])) {
                $service = $this->resolveServiceDetails($mdnsID, $service);
            }

            $ip = $this->determineIp($service);
            if ($ip === '') {
                continue;
            }

            $info = $this->probeWledInfo($ip);
            $mac  = strtoupper((string)($info['mac'] ?? ''));
            $key  = $mac !== '' ? 'mac:' . $mac : 'ip:' . $ip;
            $name = (string)($info['name'] ?? ($service['Name'] ?? 'WLED'));
            $ver  = (string)($info['ver'] ?? 'N/A');
            $url  = sprintf('ws://%s/ws', $ip);

            $devices[$key] = [
                'name'    => $name,
                'host'    => $ip,
                'version' => $ver,
                'url'     => $url
            ];
            $this->debugExpert(__FUNCTION__, 'Device discovered', ['name' => $name, 'host' => $ip]);
        }

        return array_values($devices);
    }

    private function resolveServiceDetails(int $mdnsID, array $service): array
    {
        try {
            $details    = ZC_QueryService($mdnsID, $service['Name'], $service['Type'], $service['Domain']);
            $detailItem = $details[0] ?? $details;
            return array_merge($service, $detailItem);
        } catch (Throwable $e) {
            $this->debugExpert(__FUNCTION__, 'Resolve failed', ['error' => $e->getMessage()]);
        }

        return $service;
    }

    private function determineIp(array $service): string
    {
        if (!empty($service['IPv4']) && isset($service['IPv4'][0])) {
            return (string)$service['IPv4'][0];
        }

        if (!empty($service['Host'])) {
            $host = rtrim((string)$service['Host'], '.');
            return gethostbyname($host);
        }

        return '';
    }

    private function probeWledInfo(string $host): array
    {
        $jsonData = @file_get_contents(sprintf('http://%s/json/info', $host), false, stream_context_create([
                                                                                                               'http' => ['timeout' => 1]
                                                                                                           ]));
        if ($jsonData === false) {
            return [];
        }

        try {
            $decoded = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function mapDevicesToForm(array $foundDevices, array $existingSplitterIDs): array
    {
        $formValues = [];
        $hostToId   = [];

        foreach ($existingSplitterIDs as $id) {
            $host = $this->getSplitterHost($id);
            if ($host !== '') {
                $hostToId[$host] = $id;
            } else {
                $hostToId['__missing__' . $id] = $id;
            }
        }

        foreach ($foundDevices as $device) {
            $host       = $device['host'];
            $instanceID = $hostToId[$host] ?? 0;
            if (isset($hostToId[$host])) {
                unset($hostToId[$host]);
            }

            $formValues[] = [
                'name'       => $device['name'],
                'host'       => $host,
                'version'    => $device['version'],
                'url'        => $device['url'],
                'instanceID' => $instanceID,
                'create'     => [
                    [
                        'moduleID'      => WLEDIds::MODULE_WLED_MASTER,
                        'configuration' => new stdClass(),
                        'name'          => sprintf('WLED Master (%s)', $device['name'])
                    ],
                    [
                        'moduleID'      => WLEDIds::MODULE_WLED_SPLITTER,
                        'configuration' => new stdClass(),
                        'name'          => sprintf('WLED Splitter (%s)', $device['name'])
                    ],
                    [
                        'moduleID'      => WLEDIds::MODULE_WEBSOCKETCLIENT,
                        'configuration' => [
                            'Active' => true,
                            'URL'    => $device['url']
                        ],
                        'name'          => sprintf('WebSocket Client (%s)', $device['name'])
                    ]
                ]
            ];
        }

        foreach ($hostToId as $host => $id) {
            $displayHost = $host;
            if (str_starts_with($host, '__missing__')) {
                $displayHost = $this->Translate('unknown');
            }

            $formValues[] = [
                'name'       => IPS_GetName($id),
                'host'       => $displayHost,
                'version'    => $this->Translate('unknown'),
                'url'        => $this->Translate('offline'),
                'instanceID' => $id,
                'create'     => []
            ];
        }

        return $formValues;
    }

    private function getSplitterHost(int $splitterId): string
    {
        $splitter = IPS_GetInstance($splitterId);
        $parentId = (int)($splitter['ConnectionID'] ?? 0);
        if ($parentId <= 0) {
            return '';
        }

        $url = (string)@IPS_GetProperty($parentId, 'URL');
        if ($url === '') {
            return '';
        }

        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? $host : '';
    }

    private function getMdnsInstance(): int
    {
        $ids = IPS_GetInstanceListByModuleID(WLEDIds::MODULE_MDNS);
        if (count($ids) > 0) {
            return $ids[0];
        }

        $id = IPS_CreateInstance(WLEDIds::MODULE_MDNS);
        if ($id === 0) {
            throw new RuntimeException('DNS-SD Control instance could not be created.');
        }

        $this->debugExpert(__FUNCTION__, 'Created DNS-SD Control instance', ['instanceID' => $id], true);
        IPS_SetName($id, 'DNS-SD Control');
        IPS_ApplyChanges($id);
        return $id;
    }

    private function formElements(): array
    {
        return [
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Expert',
                'items'   => [
                    [
                        'type'    => 'CheckBox',
                        'name'    => 'EnableExpertDebug',
                        'caption' => 'Enable extended debug output'
                    ]
                ]
            ]
        ];
    }

    private function formActions(): array
    {
        $devices = json_decode($this->GetBuffer(self::BUFFER_DEVICES), false, 512, JSON_THROW_ON_ERROR);

        return [
            [
                'name'          => 'searchingInfo',
                'type'          => 'ProgressBar',
                'caption'       => 'Searching for WLED instances via mDNS (wait 2s)...',
                'indeterminate' => true,
                'visible'       => count($devices) === 0
            ],
            [
                'name'     => 'configurator',
                'type'     => 'Configurator',
                'rowCount' => 20,
                'add'      => false,
                'delete'   => true,
                'sort'     => [
                    'column'    => 'host',
                    'direction' => 'ascending'
                ],
                'columns'  => [
                    [
                        'caption' => 'Name',
                        'name'    => 'name',
                        'width'   => '220px'
                    ],
                    [
                        'caption' => 'IP Address',
                        'name'    => 'host',
                        'width'   => '140px'
                    ],
                    [
                        'caption' => 'Version',
                        'name'    => 'version',
                        'width'   => '100px'
                    ],
                    [
                        'caption' => 'WebSocket URL',
                        'name'    => 'url',
                        'width'   => 'auto'
                    ]
                ],
                'values'   => $devices
            ]
        ];
    }
}
