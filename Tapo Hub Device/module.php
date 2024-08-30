<?php

declare(strict_types=1);
eval('declare(strict_types=1);namespace TapoHubDevice {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace TapoHubDevice {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/VariableProfileHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/TapoLib.php';

/**
 * TapoHubDevice Klasse für die Anbindung von TP-Link tapo Geräte welche mit Hubs verbunden werden.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.70
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method void RegisterProfileFloat(string $Name, string $Icon, string $Prefix, string $Suffix, float $MinValue, float $MaxValue, float $StepSize, int $Digits)
 */
class TapoHubDevice extends IPSModule
{
    use \TapoHubDevice\DebugHelper;
    use \TapoHubDevice\VariableProfileHelper;

    public function Create()
    {
        $this->RegisterPropertyString(\TpLink\Property::DeviceId, '');
        $this->RegisterPropertyBoolean(\TpLink\Property::AutoRename, false);
        $this->RegisterAttributeString(\TpLink\Attribute::Category, '');
        //Tapo.Temperature.Room
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterProfileFloat(\TpLink\VariableProfile::TargetTemperature, 'Temperature', '', ' °C', 5, 30, 0.5, 1);

        $DeviceId = $this->ReadPropertyString(\TpLink\Property::DeviceId);
        if ($DeviceId) {
            $this->SetReceiveDataFilter('.*"' . \TpLink\Property::DeviceId . '":"' . $DeviceId . '".*');
        } else {
            $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        }
    }

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        $Method = $Data[\TpLink\Api\Protocol::Method];
        $Result = $Data[\TpLink\Api\Result];
        $this->SendDebug('Event[' . $Method . ']', $Result, 0);
        switch ($Method) {
            case \TpLink\Api\Method::GetDeviceInfo:
                $this->SetVariables($Result);
                break;
        }
        return '';
    }

    public function RequestAction($Ident, $Value)
    {
        $AllIdents = $this->GetModuleIdents();
        if (array_key_exists($Ident, $AllIdents)) {
            if ($AllIdents[$Ident][\TpLink\HasAction]) {
                $Values[$Ident] = $Value;
                if ($this->SendInfoVariables($Values)) {
                    $this->SetValue($Ident, $Value);
                }
            }
            return;
        }
        set_error_handler([$this, 'ModulErrorHandler']);
        trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
        restore_error_handler();
    }

    protected function SendInfoVariables(array $Values)
    {
        $SendValues = [];
        $AllIdents = $this->GetModuleIdents();
        foreach ($Values as $Ident => $Value) {
            if (!array_key_exists($Ident, $AllIdents)) {
                continue;
            }
            if (array_key_exists(\TpLink\SendFunction, $AllIdents[$Ident])) {
                $SendValues = array_merge($SendValues, $this->{$AllIdents[$Ident][\TpLink\SendFunction]}($Value));
                continue;
            }
            $SendValues[$Ident] = $Value;
        }
        if (!count($SendValues)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return $this->SendRequest(\TpLink\Api\Method::SetDeviceInfo, $SendValues);
    }

    protected function SendRequest(string $Method, array $Params = [])
    {
        $this->SendDebug('Send Method', $Method, 0);
        $this->SendDebug('Send Params', $Params, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'                       => \TpLink\GUID::ChildSendToHub,
                \TpLink\Api\Protocol::Method   => $Method,
                \TpLink\Property::DeviceId     => $this->ReadPropertyString(\TpLink\Property::DeviceId),
                \TpLink\Api\Protocol::Params   => $Params
            ]
        ));
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        $Error_code = $Result[\TpLink\Api\ErrorCode];
        if ($Error_code != 0) {
            if (array_key_exists($Error_code, \TpLink\Api\Protocol::$ErrorCodes)) {
                $msg = \TpLink\Api\Protocol::$ErrorCodes[$Error_code];
            } else {
                $msg = $Error_code;
            }
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate($msg), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return $Result;
    }

    protected function SetVariables(array $Values)
    {
        if (array_key_exists(\TpLink\Api\Result::Model, $Values)) {
            $this->SetSummary($Values[\TpLink\Api\Result::Model]);
        }
        if (array_key_exists(\TpLink\Api\Result::Category, $Values)) {
            $Categories = explode('.', $Values[\TpLink\Api\Result::Category]);
            $Category = end($Categories);
            $this->SendDebug('Category', $Category, 0);
            $this->WriteAttributeString(\TpLink\Attribute::Category, $Category);
        }
        if (array_key_exists(\TpLink\Api\Result::Nickname, $Values)) {
            $Name = base64_decode($Values[\TpLink\Api\Result::Nickname]);
            if ($this->ReadPropertyBoolean(\TpLink\Property::AutoRename) && (IPS_GetName($this->InstanceID) != $Name) && ($Name != '')) {
                IPS_SetName($this->InstanceID, $Name);
            }
        }

        foreach ($this->GetModuleIdents() as $Ident => $VarParams) {
            if (array_key_exists(\TpLink\ReceiveFunction, $VarParams)) {
                $Values[$Ident] = $this->{$VarParams[\TpLink\ReceiveFunction]}($Values);
                if (is_null($Values[$Ident])) {
                    continue;
                }
            } else {
                if (!array_key_exists($Ident, $Values)) {
                    continue;
                }
            }
            $this->MaintainVariable(
                $Ident,
                $this->Translate($VarParams[\TpLink\IPSVarName]),
                $VarParams[\TpLink\IPSVarType],
                $VarParams[\TpLink\IPSVarProfile],
                0,
                true
            );
            if ($VarParams[\TpLink\HasAction]) {
                $this->EnableAction($Ident);
            }
            $this->SetValue($Ident, $Values[$Ident]);
        }
    }

    private function ModulErrorHandler(int $errno, string $errstr): bool
    {
        echo $errstr . PHP_EOL;
        return true;
    }

    private function GetModuleIdents(): array
    {
        $Category = $this->ReadAttributeString(\TpLink\Attribute::Category);
        if (!$Category) {
            return [];
        }
        return \TpLink\HubChildDevicesCategory::GetVariableIdentsByCategory($Category);
    }

    private function TrvStateToString(array $Values)
    {
        $State = array_shift($Values[\TpLink\VariableIdentTrv::trv_states]);
        if (!$State) {
            $State = '';
        }
        return $State;
    }
}
