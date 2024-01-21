<?php

declare(strict_types=1);
eval('declare(strict_types=1);namespace TapoDiscovery {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/TapoCrypt.php';
require_once dirname(__DIR__) . '/libs/TapoLib.php';

/**
 * TapoDiscovery
 * TapoDiscovery Klasse für das auffinden von Geräten im Netzwerk.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.60
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 *
 */
class TapoDiscovery extends IPSModule
{
    use \TapoDiscovery\DebugHelper;

    public const DISCOVERY_TIMEOUT = 3;
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterAttributeString(\TpLink\Attribute::Username, '');
        $this->RegisterAttributeString(\TpLink\Attribute::Password, '');
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == 'Save') {
            $Data = explode(':', $Value);
            $this->WriteAttributeString(\TpLink\Attribute::Username, urldecode($Data[0]));
            $this->WriteAttributeString(\TpLink\Attribute::Password, urldecode($Data[1]));
            $this->ReloadForm();
            return;
        }
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }

        if (IPS_GetOption('NATSupport') && strpos(IPS_GetKernelPlatform(), 'Docker')) {
            // not supported. Docker cannot forward Broadcast :(
            $Form['actions'][2]['visible'] = true;
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $Form['actions'][0]['items'][0]['items'][0]['value'] = $this->ReadAttributeString(\TpLink\Attribute::Username);
        $Form['actions'][0]['items'][0]['items'][1]['value'] = $this->ReadAttributeString(\TpLink\Attribute::Password);
        $Form['actions'][1]['values'] = $this->GetDevices();
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }

    private function GetDevices(): array
    {
        $Devices = $this->Discover();
        $this->SendDebug('NetworkDevices', $Devices, 0);
        $IPSDevices = $this->GetIPSInstances();
        $this->SendDebug('IPS Devices', $IPSDevices, 0);
        $Values = [];
        foreach ($Devices as $Device) {
            $Guid = \TpLink\DeviceModel::GetGuidByDeviceModel($Device[\TpLink\Api\Result::DeviceModel]);
            if (!$Guid) {
                continue;
            }
            $InstanceID = array_search(strtoupper($Device[\TpLink\Api\Result::Mac]), $IPSDevices);
            if ($InstanceID) {
                $Open = false;
                if ($Guid == \TpLink\GUID::HubConfigurator) {
                    $Guid = \TpLink\GUID::Hub;
                    $ConnectionID = IPS_GetInstance($InstanceID)['ConnectionID'];
                    if (IPS_InstanceExists($ConnectionID)) {
                        $Open = IPS_GetProperty($ConnectionID, \TpLink\Property::Open);
                    }
                } else {
                    $Open = IPS_GetProperty($InstanceID, \TpLink\Property::Open);
                }
                unset($IPSDevices[$InstanceID]);
            } else {
                $Open = true;
            }
            $Create = [
                'moduleID'                => $Guid,
                'configuration'           => [
                    \TpLink\Property::Open           => $Open,
                    \TpLink\Property::Host           => $Device[\TpLink\Api\Result::Ip],
                    \TpLink\Property::Mac            => $Device[\TpLink\Api\Result::Mac],
                    \TpLink\Property::Username       => $this->ReadAttributeString(\TpLink\Attribute::Username),
                    \TpLink\Property::Password       => $this->ReadAttributeString(\TpLink\Attribute::Password),
                    \TpLink\Property::Protocol       => $Device[\TpLink\Api\Result::MGT][\TpLink\Api\Result::Protocol]
                ]
            ];
            if ($Guid == \TpLink\GUID::Hub) {
                $Create = [[
                    'moduleID'                => \TpLink\GUID::HubConfigurator,
                    'configuration'           => new stdClass()
                ], $Create];
            }
            $Values[] = [
                'host'               => $Device[\TpLink\Api\Result::Ip],
                'mac'                => strtoupper($Device[\TpLink\Api\Result::Mac]),
                'name'               => ($InstanceID ? IPS_GetName($InstanceID) : $Device[\TpLink\Api\Result::DeviceType]),
                'device_type'        => $Device[\TpLink\Api\Result::DeviceType],
                'device_model'       => $Device[\TpLink\Api\Result::DeviceModel],
                'instanceID'         => ($InstanceID ? $InstanceID : 0),
                'create'             => $Create
            ];
        }
        foreach ($IPSDevices as $InstanceID => $Mac) {
            $GUID = IPS_GetInstance($InstanceID)['ModuleInfo']['ModuleID'];
            $Host = '';
            if ($GUID == \TpLink\GUID::HubConfigurator) {
                $ConnectionID = IPS_GetInstance($InstanceID)['ConnectionID'];
                if (IPS_InstanceExists($ConnectionID)) {
                    $Host = IPS_GetProperty($ConnectionID, \TpLink\Property::Host);
                }
            } else {
                $Host = IPS_GetProperty($InstanceID, \TpLink\Property::Host);
            }

            $Values[] = [
                'host'               => $Host,
                'mac'                => $Mac,
                'name'               => IPS_GetName($InstanceID),
                'device_type'        => '',
                'device_model'       => '',
                'instanceID'         => $InstanceID
            ];
        }
        return $Values;
    }

    private function Discover(): array
    {
        $Key = (new \phpseclib\Crypt\RSA())->createKey(1024);
        $JsonPayload = \TpLink\Api\Protocol::BuildDiscoveryRequest($Key['publickey']);
        $Part1 = "\x02\x00\x00\x01" . pack('n', strlen($JsonPayload)) . "\x11\x00" . "\x01\x02\x03\x04";
        //$Part2 = "\x5A\x6B\x7C\x8D" . $JsonPayload;
        $Payload = $Part1 . hash('crc32b', $Part1 . "\x5A\x6B\x7C\x8D" . $JsonPayload, true) . $JsonPayload;
        $DevicesData = [];
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket) {
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 100000]);
            socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
            socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_TTL, 5);
            socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
            socket_bind($socket, '0.0.0.0', 0);
            $discoveryTimeout = time() + self::DISCOVERY_TIMEOUT;
            $this->SendDebug('Search', $Payload, 0);
            if (@socket_sendto($socket, $Payload, strlen($Payload), 0, '255.255.255.255', 20002) === false) {
                $this->SendDebug('Error', 'on send discovery message', 0);
                @socket_close($socket);
                return [];
            }
            $response = '';
            $IPAddress = '';
            $Port = 0;
            do {
                if (0 == @socket_recvfrom($socket, $response, 2048, 0, $IPAddress, $Port)) {
                    continue;
                }
                $Data = substr($response, 16);
                $this->SendDebug('Receive (' . $IPAddress . ')', $Data, 0);
                $JsonReceive = json_decode($Data, true);
                if (!$JsonReceive) {
                    continue;
                }
                if ($JsonReceive[\TpLink\Api\ErrorCode] != 0) {
                    continue;
                }
                $Result = $JsonReceive[\TpLink\Api\Result];
                $DevicesData[] = $Result;
            } while (time() < $discoveryTimeout);
            socket_close($socket);
        } else {
            $this->SendDebug('Error', 'on create broadcast Socket', 0);
        }
        return $DevicesData;
    }

    private function GetIPSInstances(): array
    {
        $Devices = [];
        foreach (\TpLink\DeviceModel::$DeviceModels as $GUID) {
            $InstanceIDList = IPS_GetInstanceListByModuleID($GUID);
            foreach ($InstanceIDList as $InstanceID) {
                if ($GUID == \TpLink\GUID::HubConfigurator) {
                    $Devices[$InstanceID] = '';
                    $ConnectionID = IPS_GetInstance($InstanceID)['ConnectionID'];
                    if (IPS_InstanceExists($ConnectionID)) {
                        $Devices[$InstanceID] = strtoupper(IPS_GetProperty($ConnectionID, \TpLink\Property::Mac));
                    }
                } else {
                    $Devices[$InstanceID] = strtoupper(IPS_GetProperty($InstanceID, \TpLink\Property::Mac));
                }
            }
        }
        return $Devices;
    }
}