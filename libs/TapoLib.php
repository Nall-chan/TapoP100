<?php

declare(strict_types=1);

namespace {
    eval('declare(strict_types=1);namespace Tapo {?>' . file_get_contents(__DIR__ . '/helper/BufferHelper.php') . '}');
    eval('declare(strict_types=1);namespace Tapo {?>' . file_get_contents(__DIR__ . '/helper/DebugHelper.php') . '}');
    eval('declare(strict_types=1);namespace Tapo {?>' . file_get_contents(__DIR__ . '/helper/SemaphoreHelper.php') . '}');
    eval('declare(strict_types=1);namespace Tapo {?>' . file_get_contents(__DIR__ . '/helper/VariableProfileHelper.php') . '}');

    $AutoLoader = new AutoLoaderTapoPHPSecLib('Crypt/Random');
    $AutoLoader->register();

    class AutoLoaderTapoPHPSecLib
    {
        private $namespace;

        public function __construct($namespace = null)
        {
            $this->namespace = $namespace;
        }

        public function register()
        {
            spl_autoload_register([$this, 'loadClass']);
        }

        public function loadClass($className)
        {
            $LibPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'phpseclib' . DIRECTORY_SEPARATOR;
            $file = $LibPath . str_replace(['\\', 'phpseclib3'], [DIRECTORY_SEPARATOR, 'phpseclib'], $className) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}

namespace TpLink\Api
{
    const Protocol = 'http://';
    const ErrorCode = 'error_code';
    const Result = 'result';

    class Url
    {
        public const App = '/app';
        public const InitKlap = self::App . '/handshake1';
        public const HandshakeKlap = self::App . '/handshake2';
        public const KlapRequest = self::App . '/request?';
    }

    class Method
    {
        public const GetDeviceInfo = 'get_device_info';
        public const SetDeviceInfo = 'set_device_info';
        public const CountdownRule = 'add_countdown_rule';
        public const Handshake = 'handshake';
        public const Login = 'login_device';
        public const SecurePassthrough = 'securePassthrough';
    }

    class Param
    {
        public const Username = 'username';
        public const Password = 'password';
    }

    class Result
    {
        public const Nickname = 'nickname';
        public const Response = 'response';
        public const EncryptedKey = 'key';
        public const Ip = 'ip';
        public const Mac = 'mac';
        public const DeviceType = 'device_type';
        public const DeviceModel = 'device_model';
        public const MGT = 'mgt_encrypt_schm';
        public const Protocol = 'encrypt_type';
    }

    class Protocol
    {
        public const Method = 'method';
        public const Params = 'params';
        private const ParamHandshakeKey = 'key';
        private const DiscoveryKey = 'rsa_key';
        private const requestTimeMils = 'requestTimeMils';
        private const TerminalUUID = 'terminalUUID';
        public static $ErrorCodes = [
            0    => 'Success',
            -1010=> 'Invalid Public Key Length',
            -1501=> 'Invalid Request or Credentials',
            1002 => 'Incorrect Request',
            1003 => 'Invalid Protocol',
            -1003=> 'JSON formatting error',
            -1008=> 'Value out of range',
            9999 => 'Session Timeout'
        ];

        public static function BuildHandshakeRequest(string $publicKey): string
        {
            return json_encode([
                self::Method=> Method::Handshake,
                self::Params=> [
                    self::ParamHandshakeKey          => mb_convert_encoding($publicKey, 'ISO-8859-1', 'UTF-8')
                ],
                self::requestTimeMils => 0

            ]);
        }

        public static function BuildRequest(string $Method, string $TerminalUUID = '', array $Params = []): string
        {
            $Request = [
                self::Method          => $Method,
                self::requestTimeMils => 0
            ];
            if ($TerminalUUID) {
                $Request[self::TerminalUUID] = $TerminalUUID;
            }
            if (count($Params)) {
                $Request[self::Params] = $Params;
            }

            return json_encode($Request);
        }

        public static function BuildDiscoveryRequest(string $publicKey): string
        {
            return json_encode([
                self::Params=> [
                    self::DiscoveryKey=> mb_convert_encoding($publicKey, 'ISO-8859-1', 'UTF-8')
                ]
            ]);
        }
    }

    class TpProtocol
    {
        private const Token = 'token';
        private const Request = 'request';

        public static function GetUrlWithToken(string $Host, string $Token): string
        {
            return Protocol . $Host . Url::App . '?' . http_build_query([self::Token => $Token]);
        }

        public static function BuildSecurePassthroughRequest(string $EncryptedPayload): string
        {
            return json_encode([
                Protocol::Method=> Method::SecurePassthrough,
                Protocol::Params=> [
                    self::Request=> $EncryptedPayload
                ]]);
        }
    }
}

namespace TpLink
{
    const IPSVarName = 'IPSVarName';
    const IPSVarType = 'IPSVarType';
    const IPSVarProfile = 'IPSVarProfile';
    const HasAction = 'HasAction';
    const ReceiveFunction = 'ReceiveFunction';
    const SendFunction = 'SendFunction';

    class DeviceModel
    {
        public const PlugP100 = 'P100';
        public const PlugP110 = 'P110';
        public const BulbL530 = 'L530';
        public const BulbL610 = 'L610';
        public const KH100 = 'KH100';
    }

    class GUID
    {
        public const Plug = '{AAD6F48D-C23F-4C59-8049-A9746DEB699B}';
        public const PlugEnergy = '{B18B6CAA-AB46-495D-9A7A-85FA3A83113A}';
        public const BulbL530 = '{3C59DCC3-4441-4E1C-A59C-9F8D26CE2E82}';
        public const BulbL610 = '{1B9D73D6-853D-4E2E-9755-2273FD7A6123}';
        //public const KH100 = '{1EDD1EB2-6885-4D87-BA00-9328D74A85C4}';

        public static $TapoDevices = [
            DeviceModel::PlugP100 => self::Plug,
            DeviceModel::PlugP110 => self::PlugEnergy,
            DeviceModel::BulbL530 => self::BulbL530,
            DeviceModel::BulbL610 => self::BulbL610,
            //DeviceModel::KH100    => self::KH100,
        ];

        public static function GetByModel(string $Model)
        {
            if (!array_key_exists($Model, self::$TapoDevices)) {
                return false;
            }
            return self::$TapoDevices[$Model];
        }
    }

    class Property
    {
        public const Open = 'Open';
        public const Host = 'Host';
        public const Mac = 'Mac';
        public const Username = 'Username';
        public const Password = 'Password';
        public const Interval = 'Interval';
        public const AutoRename = 'AutoRename';
        public const Protocol = 'Protocol';
    }

    class Attribute
    {
        public const Username = 'Username';
        public const Password = 'Password';
    }

    class Timer
    {
        public const RequestState = 'RequestState';
    }

    class VariableIdent
    {
        public const device_on = 'device_on';
        public const rssi = 'rssi'; //todo

        public static $Variables = [
            self::device_on=> [
                IPSVarName   => 'State',
                IPSVarType   => VARIABLETYPE_BOOLEAN,
                IPSVarProfile=> VariableProfile::Switch,
                HasAction    => true
            ],
            self::rssi => [
                IPSVarName              => 'Rssi',
                IPSVarType              => VARIABLETYPE_INTEGER,
                IPSVarProfile           => '',
                HasAction               => false
            ]
        ];
    }

    class VariableIdentEnergySocket
    {
        public const today_runtime = 'today_runtime';
        public const month_runtime = 'month_runtime';
        public const today_runtime_raw = 'today_runtime_raw';
        public const month_runtime_raw = 'month_runtime_raw';
        public const today_energy = 'today_energy';
        public const month_energy = 'month_energy';
        public const current_power = 'current_power';
    }

    class VariableIdentLight
    {
        public const overheated = 'overheated';
        public const brightness = 'brightness';

        public static $Variables = [
            self::overheated=> [
                IPSVarName   => 'Overheated',
                IPSVarType   => VARIABLETYPE_BOOLEAN,
                IPSVarProfile=> '~Alert',
                HasAction    => false
            ],
            self::brightness=> [
                IPSVarName   => 'Brightness',
                IPSVarType   => VARIABLETYPE_INTEGER,
                IPSVarProfile=> VariableProfile::Brightness,
                HasAction    => true
            ]
        ];
    }

    class VariableIdentLightColor
    {
        public const overheated = 'overheated';
        public const brightness = 'brightness';
        public const hue = 'hue';
        public const saturation = 'saturation';
        public const color_temp = 'color_temp';
        public const dynamic_light_effect_enable = 'dynamic_light_effect_enable';
        public const color_rgb = 'color_rgb';

        public static $Variables = [
            self::overheated=> [
                IPSVarName   => 'Overheated',
                IPSVarType   => VARIABLETYPE_BOOLEAN,
                IPSVarProfile=> '~Alert',
                HasAction    => false
            ],
            self::brightness=> [
                IPSVarName   => 'Brightness',
                IPSVarType   => VARIABLETYPE_INTEGER,
                IPSVarProfile=> VariableProfile::Brightness,
                HasAction    => true
            ],
            self::color_temp=> [
                IPSVarName   => 'Color temp',
                IPSVarType   => VARIABLETYPE_INTEGER,
                IPSVarProfile=> VariableProfile::ColorTemp,
                HasAction    => true
            ],
            self::color_rgb=> [
                IPSVarName     => 'Color',
                IPSVarType     => VARIABLETYPE_INTEGER,
                IPSVarProfile  => VariableProfile::HexColor,
                HasAction      => true,
                ReceiveFunction=> 'HSVtoRGB',
                SendFunction   => 'RGBtoHSV'
            ],
        ];
    }
    class VariableIdentTrv
    {
        public const target_temp = 'target_temp';
        public const temp_offset = 'temp_offset';
        public const frost_protection_on = 'frost_protection_on';
        public const child_protection = 'child_protection';

        public static $Variables = [
            self::target_temp=> [
                IPSVarName   => 'Setpoint temperature',
                IPSVarType   => VARIABLETYPE_FLOAT,
                IPSVarProfile=> VariableProfile::TargetTemperature,
                HasAction    => true
            ],
            self::frost_protection_on=> [
                IPSVarName     => 'Frost protection',
                IPSVarType     => VARIABLETYPE_BOOLEAN,
                IPSVarProfile  => VariableProfile::Switch,
                HasAction      => true
            ],
            self::child_protection=> [
                IPSVarName     => 'Child Protection',
                IPSVarType     => VARIABLETYPE_BOOLEAN,
                IPSVarProfile  => VariableProfile::Switch,
                HasAction      => true
            ],
        ];
    }

    class VariableProfile
    {
        public const Runtime = 'Tapo.Runtime';
        public const ColorTemp = 'Tapo.ColorTemp';
        public const Brightness = 'Tapo.Brightness';
        public const Switch = '~Switch';
        public const HexColor = '~HexColor';
        public const TargetTemperature = '~Temperature.Room';
    }

    class KelvinTable
    {
        private static $Table = [
            2500=> [255, 161, 72],
            2600=> [255, 165, 79],
            2700=> [255, 169, 87],
            2800=> [255, 173, 94],
            2900=> [255, 177, 101],
            3000=> [255, 180, 107],
            3100=> [255, 184, 114],
            3200=> [255, 187, 120],
            3300=> [255, 190, 126],
            3400=> [255, 193, 132],
            3500=> [255, 196, 137],
            3600=> [255, 199, 143],
            3700=> [255, 201, 148],
            3800=> [255, 204, 153],
            3900=> [255, 206, 159],
            4000=> [255, 209, 163],
            4100=> [255, 211, 168],
            4200=> [255, 213, 173],
            4300=> [255, 215, 177],
            4400=> [255, 217, 182],
            4500=> [255, 219, 186],
            4600=> [255, 221, 190],
            4700=> [255, 223, 194],
            4800=> [255, 225, 198],
            4900=> [255, 227, 202],
            5000=> [255, 228, 206],
            5100=> [255, 230, 210],
            5200=> [255, 232, 213],
            5300=> [255, 233, 217],
            5400=> [255, 235, 220],
            5500=> [255, 236, 224],
            5600=> [255, 238, 227],
            5700=> [255, 239, 230],
            5800=> [255, 240, 233],
            5900=> [255, 242, 236],
            6000=> [255, 243, 239],
            6100=> [255, 244, 242],
            6200=> [255, 245, 245],
            6300=> [255, 246, 247],
            6400=> [255, 248, 251],
            6500=> [255, 249, 253]
        ];

        public static function ToRGB(int $Kelvin)
        {
            foreach (self::$Table as $Key => $RGB) {
                if ($Key < $Kelvin) {
                    continue;
                }
                break;
            }
            return $RGB;
        }
    }

    class TpLinkCipher
    {
        private $key;
        private $iv;

        public function __construct($key, $iv)
        {
            $this->key = $key;
            $this->iv = $iv;
        }
        public function encrypt($data)
        {
            $cipher = new \phpseclib\Crypt\AES('cbc');
            $cipher->enablePadding();
            $cipher->setIV($this->iv);
            $cipher->setKey($this->key);
            $encrypted = $cipher->encrypt($data);
            return base64_encode($encrypted);
        }

        public function decrypt($data)
        {
            $cipher = new \phpseclib\Crypt\AES('cbc');
            $cipher->enablePadding();
            $cipher->setIV($this->iv);
            $cipher->setKey($this->key);
            $decrypted = $cipher->decrypt(base64_decode($data));
            return $decrypted;
        }
    }

    class KlapCipher
    {
        private $key;
        private $seq;
        private $iv;
        private $sig;

        public function __construct(string $lSeed, string $rSeed, string $uHash, ?int $Sequenz)
        {
            $this->key = substr(hash('sha256', 'lsk' . $lSeed . $rSeed . $uHash, true), 0, 16);
            $this->sig = substr(hash('sha256', 'ldk' . $lSeed . $rSeed . $uHash, true), 0, 28);
            $iv = hash('sha256', 'iv' . $lSeed . $rSeed . $uHash, true);
            if (is_null($Sequenz)) {
                $this->seq = unpack('N', substr($iv, -4))[1];
            } else {
                $this->seq = $Sequenz;
            }
            $this->iv = substr($iv, 0, 12);
        }

        public function encrypt(string $data): string
        {
            $this->seq++;
            $cipher = new \phpseclib\Crypt\AES('cbc');
            $cipher->enablePadding();
            $cipher->setIV($this->iv . pack('N', $this->seq));
            $cipher->setKey($this->key);
            $encrypted = $cipher->encrypt($data);
            $signature = hash('sha256', $this->sig . pack('N', $this->seq) . $encrypted, true);
            return $signature . $encrypted;
        }

        public function getSequenz(): int
        {
            return $this->seq;
        }

        public function decrypt(string $data): string
        {
            $cipher = new \phpseclib\Crypt\AES('cbc');
            $cipher->enablePadding();
            $cipher->setIV($this->iv . pack('N', $this->seq));
            $cipher->setKey($this->key);
            $decrypted = $cipher->decrypt(substr($data, 32));
            return $decrypted;
        }
    }

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
        use TpLinkKlap;
        use TpLinkSecurePassthroug;

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
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
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
            $AllIdents = $this->GetModuleIdents();
            if (array_key_exists($Ident, $AllIdents)) {
                if ($AllIdents[$Ident][\TpLink\HasAction]) {
                    if ($this->SetDeviceInfoVariables([$Ident => $Value])) {
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

        public function GetDeviceInfo()
        {
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetDeviceInfo);
            $this->SendDebug(__FUNCTION__, $Request, 0);
            $Response = $this->SendRequest($Request);
            if ($Response === '') {
                return false;
            }
            $json = json_decode($Response, true);
            if ($json[\TpLink\Api\ErrorCode] != 0) {
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate(\TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]]), E_USER_NOTICE);
                restore_error_handler();
                return false;
            }
            $Result = $json[\TpLink\Api\Result];
            $Name = base64_decode($Result[\TpLink\Api\Result::Nickname]);
            if ($this->ReadPropertyBoolean(\TpLink\Property::AutoRename) && (IPS_GetName($this->InstanceID) != $Name) && ($Name != '')) {
                IPS_SetName($this->InstanceID, $Name);
            }
            return $Result;
        }

        protected function SetVariables(array $Values)
        {
            foreach ($this->GetModuleIdents() as $Ident => $VarParams) {
                if (!array_key_exists($Ident, $Values)) {
                    if (!array_key_exists(\TpLink\ReceiveFunction, $VarParams)) {
                        continue;
                    }
                    $Values[$Ident] = $this->{$VarParams[\TpLink\ReceiveFunction]}($Values);
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
        protected function SetDeviceInfoVariables(array $Values)
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
            return $this->SetDeviceInfo($SendValues);
        }

        protected function SetDeviceInfo(array $Values)
        {
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::SetDeviceInfo, $this->terminalUUID, $Values);
            $Response = $this->SendRequest($Request);
            if ($Response === '') {
                return false;
            }
            $json = json_decode($Response, true);
            if ($json[\TpLink\Api\ErrorCode] != 0) {
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate(\TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]]), E_USER_NOTICE);
                restore_error_handler();
                return false;
            }
            return true;
        }

        protected function SendRequest(string $Request): string
        {
            $this->SendDebug(__FUNCTION__, $Request, 0);
            if ($this->GetStatus() != IS_ACTIVE) {
                if ($this->ReadPropertyBoolean(\TpLink\Property::Open)) {
                    if (!$this->Init()) {
                        set_error_handler([$this, 'ModulErrorHandler']);
                        trigger_error($this->Translate('Error on reconnect'), E_USER_NOTICE);
                        restore_error_handler();
                        $this->SetStatus(IS_EBASE + 1);
                        return '';
                    }
                } else {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
                    restore_error_handler();
                    return '';
                }
            }
            if ($this->KlapRemoteSeed !== '') {
                return $this->KlapEncryptedRequest($Request);
            }
            if ($this->token !== '') {
                return $this->EncryptedRequest($Request);
            }
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
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
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

    trait TpLinkKlap
    {
        private function GenerateKlapAuthHash(string $Username, string $Password): string
        {
            return hash('sha256', sha1(mb_convert_encoding($Username, 'UTF-8'), true) .
                    sha1(mb_convert_encoding($Password, 'UTF-8'), true), true);
        }

        private function InitKlap(): bool
        {
            $UserHash = $this->GenerateKlapAuthHash(
                $this->ReadPropertyString(\TpLink\Property::Username),
                $this->ReadPropertyString(\TpLink\Property::Password)
            );
            $Url = \TpLink\Api\Protocol . $this->ReadPropertyString(\TpLink\Property::Host) . \TpLink\Api\Url::InitKlap;
            $Payload = random_bytes(16);
            $this->SendDebug('Init Klap', $Payload, 0);
            $this->cookie = '';
            $Result = $this->CurlRequest($Url, $Payload, true);
            $this->SendDebug('Init Klap Result', $Result, 0);
            if ($Result === false) {
                return false;
            }
            $RemoteSeed = substr($Result, 0, 16);
            $ServerHash = substr($Result, 16);
            $UserTest = hash('sha256', $Payload . $RemoteSeed . $UserHash, true);
            $this->SendDebug('Config User', $UserTest, 0);
            if ($ServerHash == $UserTest) {
                $this->KlapLocalSeed = $Payload;
                $this->KlapRemoteSeed = $RemoteSeed;
                $this->KlapUserHash = $UserHash;
                return true;
            }
            $UserHash = $this->GenerateKlapAuthHash(
                'kasa@tp-link.net',
                'kasaSetup'
            );
            $UserTest = hash('sha256', $Payload . $RemoteSeed . $UserHash, true);
            $this->SendDebug('Generic User', $UserTest, 0);
            if ($ServerHash == $UserTest) {
                $this->KlapLocalSeed = $Payload;
                $this->KlapRemoteSeed = $RemoteSeed;
                $this->KlapUserHash = $UserHash;
                return true;
            }
            $UserHash = $this->GenerateKlapAuthHash(
                '',
                ''
            );
            $UserTest = hash('sha256', $Payload . $RemoteSeed . $UserHash, true);
            $this->SendDebug('Empty User', $UserTest, 0);
            if ($ServerHash == $UserTest) {
                $this->KlapLocalSeed = $Payload;
                $this->KlapRemoteSeed = $RemoteSeed;
                $this->KlapUserHash = $UserHash;
                return true;
            }
            return false;
        }

        private function HandshakeKlap(): bool
        {
            $Url = \TpLink\Api\Protocol . $this->ReadPropertyString(\TpLink\Property::Host) . \TpLink\Api\Url::HandshakeKlap;
            $Payload = hash('sha256', $this->KlapRemoteSeed . $this->KlapLocalSeed . $this->KlapUserHash, true);
            $Result = $this->CurlRequest($Url, $Payload, true);
            $this->SendDebug('Klap Handshake Result', $Result, 0);
            return $Result !== false;
        }

        private function KlapEncryptedRequest(string $Payload): string
        {
            if ($this->KlapLocalSeed === '') {
                if (!$this->Init()) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
                    restore_error_handler();
                    $this->SetStatus(IS_EBASE + 1);
                    return '';
                }
            }
            $TpKlapCipher = new \TpLink\KlapCipher($this->KlapLocalSeed, $this->KlapRemoteSeed, $this->KlapUserHash, $this->KlapSequenz);
            $EncryptedPayload = $TpKlapCipher->encrypt($Payload);
            $this->KlapSequenz = $TpKlapCipher->getSequenz();
            $Url = \TpLink\Api\Protocol . $this->ReadPropertyString(\TpLink\Property::Host) . \TpLink\Api\Url::KlapRequest . http_build_query(['seq'=>$this->KlapSequenz]);
            $Result = $this->CurlRequest($Url, $EncryptedPayload);
            if ($Result === false) {
                if (!$this->Init()) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
                    restore_error_handler();
                    $this->SetStatus(IS_EBASE + 1);
                    return '';
                } else {
                    return $this->KlapEncryptedRequest($Payload);
                }
            }
            $decryptedResponse = $TpKlapCipher->decrypt($Result);
            $this->SendDebug('Response', $decryptedResponse, 0);
            $json = json_decode($decryptedResponse, true);
            if ($json[\TpLink\Api\ErrorCode] == 9999) {
                // Session Timeout, try to reconnect
                $this->SendDebug('Session Timeout', '', 0);
                if (!$this->Init()) {
                    $this->SetStatus(IS_EBASE + 1);
                }
                return '';
            }
            if ($json[\TpLink\Api\ErrorCode] != 0) {
                if (array_key_exists($json[\TpLink\Api\ErrorCode], \TpLink\Api\Protocol::$ErrorCodes)) {
                    $msg = \TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]];
                } else {
                    $msg = $decryptedResponse;
                }
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate($msg), E_USER_NOTICE);
                restore_error_handler();
                return '';
            }
            return $decryptedResponse;
        }
    }

    trait TpLinkSecurePassthroug
    {
        private function Handshake()
        {
            $Key = (new \phpseclib\Crypt\RSA())->createKey(1024);
            $privateKey = $Key['privatekey'];
            $publicKey = $Key['publickey'];
            $Url = \TpLink\Api\Protocol . $this->ReadPropertyString(\TpLink\Property::Host) . \TpLink\Api\Url::App;
            $Payload = \TpLink\Api\Protocol::BuildHandshakeRequest($publicKey);
            $this->SendDebug('Handshake', $Payload, 0);
            $this->cookie = '';
            $Result = $this->CurlRequest($Url, $Payload, true);
            $this->SendDebug('Handshake Result', $Result, 0);
            if ($Result === false) {
                return false;
            }
            $json = json_decode($Result, true);
            if ($json[\TpLink\Api\ErrorCode] != 0) {
                return $json[\TpLink\Api\ErrorCode];
            }
            $encryptedKey = $json[\TpLink\Api\Result][\TpLink\Api\Result::EncryptedKey];
            $ciphertext = base64_decode($encryptedKey);
            $rsa = new \phpseclib\Crypt\RSA();
            $rsa->loadKey($privateKey);
            $Bytes = $rsa->_rsaes_pkcs1_v1_5_decrypt($ciphertext);
            $Data = str_split($Bytes, 16);
            $this->TpLinkCipherKey = $Data[0];
            $this->TpLinkCipherIV = $Data[1];
            return true;
        }

        private function Login(): bool
        {
            $Url = \TpLink\Api\Protocol . $this->ReadPropertyString(\TpLink\Property::Host) . \TpLink\Api\Url::App;
            $Payload = \TpLink\Api\Protocol::BuildRequest(
                \TpLink\Api\Method::Login,
                '',
                [
                    \TpLink\Api\Param::Password => base64_encode($this->ReadPropertyString(\TpLink\Property::Password)),
                    \TpLink\Api\Param::Username => base64_encode(sha1($this->ReadPropertyString(\TpLink\Property::Username)))
                ]
            );
            $this->SendDebug(__FUNCTION__, $Payload, 0);
            $tp_link_cipher = new \TpLink\TpLinkCipher($this->TpLinkCipherKey, $this->TpLinkCipherIV);
            $EncryptedPayload = $tp_link_cipher->encrypt($Payload);
            $SecurePassthroughPayload = \TpLink\Api\TpProtocol::BuildSecurePassthroughRequest($EncryptedPayload);
            $Result = $this->CurlRequest($Url, $SecurePassthroughPayload);
            if ($Result === false) {
                return false;
            }
            $json = json_decode($tp_link_cipher->decrypt(json_decode($Result, true)[\TpLink\Api\Result][\TpLink\Api\Result::Response]), true);
            $this->SendDebug(__FUNCTION__ . ' Result', $json, 0);
            if ($json[\TpLink\Api\ErrorCode] == 0) {
                $this->token = $json[\TpLink\Api\Result]['token'];
                return true;
            }
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate(\TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]]), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }

        private function EncryptedRequest(string $Payload): string
        {
            $Url = \TpLink\Api\TpProtocol::GetUrlWithToken($this->ReadPropertyString(\TpLink\Property::Host), $this->token);
            $tp_link_cipher = new \TpLink\TpLinkCipher($this->TpLinkCipherKey, $this->TpLinkCipherIV);
            $EncryptedPayload = $tp_link_cipher->encrypt($Payload);
            $SecurePassthroughPayload = \TpLink\Api\TpProtocol::BuildSecurePassthroughRequest($EncryptedPayload);
            $Result = $this->CurlRequest($Url, $SecurePassthroughPayload);
            if ($Result === false) {
                return '';
            }
            $this->SendDebug('Response', $Result, 0);
            $json = json_decode($Result, true);

            if (in_array($json[\TpLink\Api\ErrorCode], [9999, 1003])) {
                // Session Timeout, try to reconnect
                $this->SendDebug('Session Timeout', '', 0);
                if (!$this->Init()) {
                    $this->SetStatus(IS_EBASE + 1);
                } else {
                    return $this->EncryptedRequest($Payload);
                }
                return '';
            }
            if ($json[\TpLink\Api\ErrorCode] != 0) {
                if (array_key_exists($json[\TpLink\Api\ErrorCode], \TpLink\Api\Protocol::$ErrorCodes)) {
                    $msg = \TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]];
                } else {
                    $msg = $Result;
                }
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate($msg), E_USER_NOTICE);
                restore_error_handler();
                return '';
            }
            return $tp_link_cipher->decrypt($json[\TpLink\Api\Result][\TpLink\Api\Result::Response]);
        }
    }
}

