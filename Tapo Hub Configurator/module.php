<?php

declare(strict_types=1);
eval('declare(strict_types=1);namespace TapoHubConfigurator {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/TapoLib.php';

/**
 * TapoHubConfigurator Klasse für das anlegen von Tapo Hub Devices.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.65
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class TapoHubConfigurator extends IPSModule
{
    use \TapoHubConfigurator\DebugHelper;

    private int $ParentID;

    public function Create()
    {
        parent::Create();
        $this->RequireParent('{1EDD1EB2-6885-4D87-BA00-9328D74A85C4}');
    }

    public function ApplyChanges()
    {
        $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function SendRequest(string $Method, array $Params = [])
    {
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'                       => \TpLink\GUID::ChildSendToHub,
                \TpLink\Api\Protocol::Method   => $Method,
                \TpLink\Property::DeviceId     => '',
                \TpLink\Api\Protocol::Params   => $Params
            ]
        ));
        $Result = null;
        if ($Ret) {
            $Result = unserialize($Ret);
        }
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Values = [];
        $Devices = [];
        if ($this->HasActiveParent()) {
            $Devices = $this->GetDevicesFromHub();
        }
        $this->SendDebug('Devices', $Devices, 0);
        $IPSDevices = $this->GetInstanceList();
        $this->SendDebug('IPSDevices', $IPSDevices, 0);
        foreach ($Devices as $Device) {
            $InstanceID = array_search(
                [
                    'moduleID'                   => $Device['moduleID'],
                    \TpLink\Api\Result::DeviceID => $Device[\TpLink\Api\Result::DeviceID]
                ],
                $IPSDevices
            );
            if ($InstanceID) {
                unset($IPSDevices[$InstanceID]);
            }
            $Values[] = [
                'DeviceId'           => $Device[\TpLink\API\Result::DeviceID],
                'name'               => ($InstanceID ? IPS_GetName($InstanceID) : base64_decode($Device[\TpLink\Api\Result::Nickname])),
                'type'               => $Device[\TpLink\Api\Result::Type],
                'model'              => $Device[\TpLink\Api\Result::Model],
                'instanceID'         => ($InstanceID ? $InstanceID : 0),
                'create'             => [
                    'moduleID'         => $Device['moduleID'],
                    'configuration'    => [
                        \TpLink\Property::DeviceId       => $Device[\TpLink\Api\Result::DeviceID]
                    ]
                ]
            ];
        }

        foreach ($IPSDevices as $InstanceID => $Data) {
            $Values[] = [
                'DeviceId'           => $Data[\TpLink\Property::DeviceId],
                'name'               => IPS_GetName($InstanceID),
                'type'               => '',
                'model'              => '',
                'instanceID'         => $InstanceID
            ];
        }
        $Form['actions'][0]['values'] = $Values;
        $this->SendDebug('Values', $Values, 0);
        return json_encode($Form);
    }

    private function GetDevicesFromHub()
    {
        $Result = $this->SendRequest(\TpLink\Api\Method::GetChildDeviceList);
        if (!$Result) {
            return [];
        }
        $List = $Result[\TpLink\Api\Result::ChildList];
        foreach ($List as $Index => $ChildDevice) {
            $Guid = \TpLink\HubChildDevicesModel::GetGuidByDeviceModel($ChildDevice[\TpLink\Api\Result::Model]);
            if ($Guid) {
                $List[$Index]['moduleID'] = $Guid;
            } else {
                unset($List[$Index]);
            }
        }
        return $List;
    }

    private function FilterInstances(int $InstanceID)
    {
        $Instance = IPS_GetInstance($InstanceID);
        if ($Instance['ModuleInfo']['ModuleID'] == \TpLink\GUID::HubConfigurator) {
            return false;
        }
        return $Instance['ConnectionID'] == $this->ParentID;
    }

    private function GetInstanceList()
    {
        $this->ParentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($this->ParentID == 0) {
            return [];
        }
        $AllInstancesOfParent = array_flip(array_filter(IPS_GetInstanceList(), [$this, 'FilterInstances']));
        foreach ($AllInstancesOfParent as $key => &$value) {
            $value = [
                'moduleID'                   => IPS_GetInstance($key)['ModuleInfo']['ModuleID'],
                \TpLink\Api\Result::DeviceID => IPS_GetProperty($key, \TpLink\Property::DeviceId)
            ];
        }
        return $AllInstancesOfParent;
    }
}
