<?php

declare(strict_types=1);

namespace {
    eval('declare(strict_types=1);namespace Tapo {?>' . file_get_contents(__DIR__ . '/helper/BufferHelper.php') . '}');
    eval('declare(strict_types=1);namespace Tapo {?>' . file_get_contents(__DIR__ . '/helper/DebugHelper.php') . '}');
    eval('declare(strict_types=1);namespace Tapo {?>' . file_get_contents(__DIR__ . '/helper/SemaphoreHelper.php') . '}');
    eval('declare(strict_types=1);namespace Tapo {?>' . file_get_contents(__DIR__ . '/helper/VariableProfileHelper.php') . '}');
    require_once 'TapoCrypt.php';
    require_once 'TapoLib.php';
}

namespace TpLink
{
    /**
     * Device
     * @property string $terminalUUID
     * @property string $token
     * @property string $cookie
     * @property string $TpLinkCipherIV
     * @property string $TpLinkCipherKey
     * @property string $KlapLocalSeed
     * @property string $KlapRemoteSeed
     * @property string $KlapUserHash
     * @property ?int $KlapSequenz
     * @property string[] $ChildIDs
     *
     * @method void RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, int $MinValue, int $MaxValue, float $StepSize)
     * @method bool SendDebug(string $Message, mixed $Data, int $Format)
     */
    class Device extends \IPSModule
    {
        use \Tapo\BufferHelper;
        use \Tapo\DebugHelper;
        use \Tapo\Semaphore;
        use \Tapo\VariableProfileHelper;
        use Crypt\Klap;
        use Crypt\SecurePassthroug;

        protected static $ModuleIdents = [];

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyBoolean(\TpLink\Property::Open, false);
            $this->RegisterPropertyString(\TpLink\Property::Host, '');
            $this->RegisterPropertyString(\TpLink\Property::Mac, '');
            $this->RegisterPropertyString(\TpLink\Property::Protocol, 'KLAP');
            $this->RegisterPropertyString(\TpLink\Property::Username, '');
            $this->RegisterPropertyString(\TpLink\Property::Password, '');
            $this->RegisterPropertyInteger(\TpLink\Property::Interval, 5);
            $this->RegisterPropertyBoolean(\TpLink\Property::AutoRename, false);
            $this->RegisterTimer(\TpLink\Timer::RequestState, 0, 'TAPOSH_RequestState($_IPS[\'TARGET\']);');
            $this->terminalUUID = self::guidv4();
            $this->InitBuffers();
            $this->ChildIDs = [];
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            $this->RegisterProfileInteger(\TpLink\VariableProfile::RuntimeSeconds, '', '', ' seconds', 0, 0, 0);
            $this->SetTimerInterval(\TpLink\Timer::RequestState, 0);
            $this->SetSummary($this->ReadPropertyString(\TpLink\Property::Host));
            $this->InitBuffers();
            if ($this->ReadPropertyBoolean(\TpLink\Property::Open)) {
                if ($this->ReadPropertyString(\TpLink\Property::Host) != '') {
                    if (!$this->Init()) {
                        $this->SetStatus(IS_EBASE + 1);
                    }
                    $this->SetTimerInterval(\TpLink\Timer::RequestState, $this->ReadPropertyInteger(\TpLink\Property::Interval) * 1000);
                    return;
                }
            } else {
                $this->SetStatus(IS_INACTIVE);
            }
        }

        public function RequestAction($Ident, $Value)
        {
            $SendIdent = $Ident;
            if (substr($Ident, 0, 4) == 'Pos_') {
                $IdentParts = explode('_', substr($Ident, 4));
                $Values[\TpLink\Api\Result::DeviceID] = $this->ChildIDs[array_shift($IdentParts)];
                $SendIdent = implode('_', $IdentParts);
            }

            $AllIdents = $this->GetModuleIdents();
            if (array_key_exists($SendIdent, $AllIdents)) {
                if ($AllIdents[$SendIdent][\TpLink\HasAction]) {
                    $Values[$SendIdent] = $Value;
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

        public function GetConfigurationForm()
        {
            return file_get_contents(__DIR__ . '/form.json');
        }

        public function Translate($Text)
        {
            $translation = json_decode(file_get_contents(__DIR__ . '/locale.json'), true);
            $language = IPS_GetSystemLanguage();
            $code = explode('_', $language)[0];
            if (isset($translation['translations'])) {
                if (isset($translation['translations'][$language])) {
                    if (isset($translation['translations'][$language][$Text])) {
                        return $translation['translations'][$language][$Text];
                    }
                } elseif (isset($translation['translations'][$code])) {
                    if (isset($translation['translations'][$code][$Text])) {
                        return $translation['translations'][$code][$Text];
                    }
                }
            }
            return $Text;
        }

        public function RequestState()
        {
            $Result = $this->GetDeviceInfo();
            if (is_array($Result)) {
                $this->SetVariables($Result);
                return true;
            }
            return false;
        }

        public function GetSysInfo()
        {
            // ControlChild
            /*
            $ChildValue = [\TpLink\VariableIdentOnOff::device_on => false];
            $ChildRequest = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::SetDeviceInfo, '', $ChildValue);
            $Values = [
                'device_id'  => '8022B958FB2A8894109B291806AE20F12107CD8101',
                'requestData'=> $ChildRequest
            ];
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::ControlChild, $this->terminalUUID, $Values);
             */

            // MultipleRequest an Child
            /*
            $ChildRequest = ['requests' => [\TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetDeviceInfo)]];
            $Values = [
                'device_id'  => '8022B958FB2A8894109B291806AE20F12107CD8101',
                'requestData'=>
                \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::MultipleRequest,'', $ChildRequest)

            ];
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::ControlChild, $this->terminalUUID, $Values);
             */

            /*
            $ControlChildValues = [
               'device_id'  => '8022B958FB2A8894109B291806AE20F12107CD8101',
               'requestData'=> \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetDeviceInfo)
            ];
            $ControlChildRequest1 = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::ControlChild, '', $ControlChildValues);

            $ControlChildRequest2 = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetDeviceInfo, '', ['device_info'  => ['device_id'  => '8022B958FB2A8894109B291806AE20F12107CD8101']]);
            //$ControlChildRequest2 = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetDeviceInfo, '', ['device_info'  => ['name'=>['basic_info']]]);
            $Values = [
               'requests' => [
                   $ControlChildRequest1,
                   $ControlChildRequest2]
            ];
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::MultipleRequest, $this->terminalUUID, $Values);

            $Request = \TpLink\Api\Protocol::BuildRequest('delete_all_rules', $this->terminalUUID, ['id'=>'C1']);
            $Request = \TpLink\Api\Protocol::BuildRequest('reboot');
            //$Request = \TpLink\Api\Protocol::BuildRequest('set_device_info', $this->terminalUUID,  ['set_led_off'=> ['off'=>2]]);
            $Response = $this->SendRequest($Request);
            if ($Response === null) {
               return false;
            }*/
        }

        public function GetDeviceInfo()
        {
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetDeviceInfo);
            $Response = $this->SendRequest($Request);
            if ($Response === null) {
                return false;
            }
            if (array_key_exists(\TpLink\Api\Result::Nickname, $Response)) {
                $Name = base64_decode($Response[\TpLink\Api\Result::Nickname]);
                if ($this->ReadPropertyBoolean(\TpLink\Property::AutoRename) && (IPS_GetName($this->InstanceID) != $Name) && ($Name != '')) {
                    IPS_SetName($this->InstanceID, $Name);
                }
            }
            return $Response;
        }

        protected function SetVariables(array $Values)
        {
            $NamePrefix = '';
            $IdentPrefix = '';
            if (array_key_exists(\TpLink\Api\Result::SlotNumber, $Values)) {
                $IdentPrefix = 'Pos_' . $Values[\TpLink\Api\Result::Position] . '_';
                if (array_key_exists(\TpLink\Api\Result::Nickname, $Values)) {
                    $NamePrefix = base64_decode($Values[\TpLink\Api\Result::Nickname]) . ' - ';
                }
            }
            foreach ($this->GetModuleIdents() as $Ident => $VarParams) {
                if (!array_key_exists($Ident, $Values)) {
                    if (!array_key_exists(\TpLink\ReceiveFunction, $VarParams)) {
                        continue;
                    }
                    $Values[$Ident] = $this->{$VarParams[\TpLink\ReceiveFunction]}($Values);
                    if (is_null($Values[$Ident])) {
                        continue;
                    }
                }

                $this->MaintainVariable(
                    $IdentPrefix . $Ident,
                    $NamePrefix . $this->Translate($VarParams[\TpLink\IPSVarName]),
                    $VarParams[\TpLink\IPSVarType],
                    $VarParams[\TpLink\IPSVarProfile],
                    0,
                    true
                );
                if ($VarParams[\TpLink\HasAction]) {
                    $this->EnableAction($IdentPrefix . $Ident);
                }
                $this->SetValue($IdentPrefix . $Ident, $Values[$Ident]);
            }
        }

        protected function SetStatus($Status)
        {
            if ($Status != IS_ACTIVE) {
                $this->InitBuffers();
            }
            if ($this->GetStatus() != $Status) {
                parent::SetStatus($Status);

                if ($Status == IS_ACTIVE) {
                    $this->RequestState();
                }
            }
            return true;
        }

        protected function SendInfoVariables(array $Values)
        {
            $SendValues = [];
            if (array_key_exists(\TpLink\api\Result::DeviceID, $Values)) {
                $SendValues[\TpLink\api\Result::DeviceID] = $Values[\TpLink\api\Result::DeviceID];
            }
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
            return $this->SetDeviceInfo($SendValues);
        }

        protected function SetDeviceInfo(array $Values)
        {
            if (array_key_exists(\TpLink\api\Result::DeviceID, $Values)) {
                $ChildID = $Values[\TpLink\api\Result::DeviceID];
                unset($Values[\TpLink\api\Result::DeviceID]);
                $ChildRequestValues = [
                    'device_id'  => $ChildID,
                    'requestData'=> \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::SetDeviceInfo, '', $Values)
                ];
                $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::ControlChild, $this->terminalUUID, $ChildRequestValues);
            } else {
                $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::SetDeviceInfo, $this->terminalUUID, $Values);
            }
            $Response = $this->SendRequest($Request);
            if ($Response === null) {
                return false;
            }
            if (isset($ChildRequestValues)) {
                $Error_code = $Response[\TpLink\Api\Result::ResponseData][\TpLink\Api\ErrorCode];
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
            }
            return true;
        }

        /*protected function SetAutOff(string $Value)
        {
            $Values[\TpLink\VariableIdentSocket::auto_off_remain_time] = 0;
            $Values[\TpLink\VariableIdentSocket::auto_off_status] = 'off';
            return $Values;
        }*/

        protected function SecondsToString(array $Values)
        {
            if (!isset($Values[\TpLink\VariableIdentSocket::on_time])) {
                return null;
            }
            return sprintf(gmdate('H \%\s i \%\s', $Values[\TpLink\VariableIdentSocket::on_time]), $this->Translate('hours'), $this->Translate('minutes'));
        }

        /**
         * SendRequest
         *
         * @param  array $TapoRequest
         * @return null|array
         */
        protected function SendRequest(array $TapoRequest)
        {
            $Request = json_encode($TapoRequest);
            $this->SendDebug(__FUNCTION__, $Request, 0);
            if ($this->GetStatus() != IS_ACTIVE) {
                if ($this->ReadPropertyBoolean(\TpLink\Property::Open)) {
                    if (!$this->Init()) {
                        set_error_handler([$this, 'ModulErrorHandler']);
                        trigger_error($this->Translate('Error on reconnect'), E_USER_NOTICE);
                        restore_error_handler();
                        $this->SetStatus(IS_EBASE + 1);
                        return null;
                    }
                } else {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
                    restore_error_handler();
                    return null;
                }
            }
            $Result = null;
            $JSON = '';
            if ($this->KlapRemoteSeed !== '') {
                $JSON = $this->KlapEncryptedRequest($Request);
            }
            if ($this->token !== '') {
                $JSON = $this->EncryptedRequest($Request);
            }
            if ($JSON != '') {
                $Result = json_decode($JSON, true);
                if ($Result[\TpLink\Api\ErrorCode] != 0) {
                    if (array_key_exists($Result[\TpLink\Api\ErrorCode], \TpLink\Api\Protocol::$ErrorCodes)) {
                        $msg = \TpLink\Api\Protocol::$ErrorCodes[$Result[\TpLink\Api\ErrorCode]];
                    } else {
                        $msg = $Result[\TpLink\Api\ErrorCode];
                    }
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate($msg), E_USER_NOTICE);
                    restore_error_handler();
                    return null;
                }
                if (array_key_exists(\TpLink\Api\Result, $Result)) {
                    $Result = $Result[\TpLink\Api\Result];
                }
            }
            return $Result;
        }

        protected function CurlDebug(int $HttpCode)
        {
            switch ($HttpCode) {
                case 0:
                    $this->SendDebug('Not connected', '', 0);
                    break;
                case 400:
                    $this->SendDebug('Bad Request', $HttpCode, 0);
                    break;
                case 401:
                    $this->SendDebug('Unauthorized Error', $HttpCode, 0);
                    break;
                case 404:
                    $this->SendDebug('Not Found Error', $HttpCode, 0);
                    break;
            }
        }

        protected function ModulErrorHandler(int $errno, string $errstr): bool
        {
            echo $errstr . PHP_EOL;
            return true;
        }

        private function GetModuleIdents()
        {
            $AllIdents = [];
            foreach (static::$ModuleIdents as $VariableIdentClassName) {
                /** @var VariableIdent $VariableIdentClassName */
                $AllIdents = array_merge($AllIdents, $VariableIdentClassName::$Variables);
            }
            return $AllIdents;
        }

        private function InitBuffers()
        {
            $this->token = '';
            $this->cookie = '';
            $this->TpLinkCipherKey = '';
            $this->TpLinkCipherIV = '';
            $this->KlapLocalSeed = '';
            $this->KlapRemoteSeed = '';
            $this->KlapUserHash = '';
            $this->KlapSequenz = null;
        }

        private function Init(): bool
        {
            switch ($this->ReadPropertyString(\TpLink\Property::Protocol)) {
                case 'AES':
                    $Result = $this->Handshake();
                    if ($Result === true) {
                        if ($this->Login()) {
                            $this->SetStatus(IS_ACTIVE);
                            return true;
                        }
                        return false;
                    }
                    if ($Result === 1003) {
                        set_error_handler([$this, 'ModulErrorHandler']);
                        trigger_error($this->Translate(\TpLink\Api\Protocol::$ErrorCodes[$Result]), E_USER_NOTICE);
                        restore_error_handler();
                    }
                    return false;
                    break;
                case 'KLAP':
                    if ($this->InitKlap()) {
                        if ($this->HandshakeKlap()) {
                            $this->SetStatus(IS_ACTIVE);
                            return true;
                        }
                    }
                    return false;
                    break;
            }
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate(\TpLink\Api\Protocol::$ErrorCodes[1003]), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }

        private function CurlRequest(string $Url, string $Payload, bool $noError = false)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $Url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $Payload);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 4000);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
            curl_setopt($ch, CURLOPT_COOKIELIST, $this->cookie);
            $Result = curl_exec($ch);
            $HttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $Cookie = curl_getinfo($ch, CURLINFO_COOKIELIST);
            curl_close($ch);
            $this->CurlDebug($HttpCode);
            if ($HttpCode == 200) {
                $this->cookie = (is_array($Cookie)) ? array_shift($Cookie) : '';
                return $Result;
            }
            if (($HttpCode == 0) && (!$noError)) {
                $this->SetStatus(IS_EBASE + 1);
            }
            return false;
        }

        private static function guidv4($data = null): string
        {
            // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
            $data = $data ?? random_bytes(16);
            assert(strlen($data) == 16);
            // Set version to 0100
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            // Set bits 6-7 to 10
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            // Output the 36 character UUID.
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
    }
}

