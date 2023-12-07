<?php

declare(strict_types=1);

namespace {
    eval('declare(strict_types=1);namespace TapoP100 {?>' . file_get_contents(__DIR__ . '/helper/BufferHelper.php') . '}');
    eval('declare(strict_types=1);namespace TapoP100 {?>' . file_get_contents(__DIR__ . '/helper/DebugHelper.php') . '}');
    eval('declare(strict_types=1);namespace TapoP100 {?>' . file_get_contents(__DIR__ . '/helper/SemaphoreHelper.php') . '}');
    eval('declare(strict_types=1);namespace TapoP100 {?>' . file_get_contents(__DIR__ . '/helper/VariableProfileHelper.php') . '}');

    $AutoLoader = new AutoLoaderTapoP100PHPSecLib('Crypt/Random');
    $AutoLoader->register();

    class AutoLoaderTapoP100PHPSecLib
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
        public const DeviceOn = 'device_on';
        public const Username = 'username';
        public const Password = 'password';
    }
    class Result
    {
        public const Result = 'result';
        public const DeviceOn = 'device_on';
        public const Nickname = 'nickname';
        public const Response = 'response';
        public const EncryptedKey = 'key';
    }
    class Protocol
    {
        public const Method = 'method';
        public const Params = 'params';
        private const ParamHandshakeKey = 'key';
        private const requestTimeMils = 'requestTimeMils';
        private const TerminalUUID = 'terminalUUID';
        public static $ErrorCodes = [
            0    => 'Success',
            -1010=> 'Invalid Public Key Length',
            -1501=> 'Invalid Request or Credentials',
            1002 => 'Incorrect Request',
            1003 => 'Invalid Protocol',
            -1003=> 'JSON formatting error ',
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
    class Property
    {
        public const Open = 'Open';
        public const Host = 'Host';
        public const Username = 'Username';
        public const Password = 'Password';
        public const Interval = 'Interval';
        public const AutoRename = 'AutoRename';
    }

    class Timer
    {
        public const RequestState = 'RequestState';
    }

    class VariableIdent
    {
        public const State = 'State';
        public const today_runtime = 'today_runtime';
        public const month_runtime = 'month_runtime';
        public const today_runtime_raw = 'today_runtime_raw';
        public const month_runtime_raw = 'month_runtime_raw';
        public const today_energy = 'today_energy';
        public const month_energy = 'month_energy';
        public const current_power = 'current_power';
    }
    class VariableProfile
    {
        public const Runtime = 'Tapo.Runtime';
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
        use \TapoP100\BufferHelper;
        use \TapoP100\DebugHelper;
        use \TapoP100\Semaphore;
        use \TapoP100\VariableProfileHelper;
        use TpLinkKlap;
        use TpLinkSecurePassthroug;

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyBoolean(\TpLink\Property::Open, false);
            $this->RegisterPropertyString(\TpLink\Property::Host, '');
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
                trigger_error(\TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]], E_USER_NOTICE);
                return false;
            }
            $Result = $json[\TpLink\Api\Result::Result];
            $Name = base64_decode($Result[\TpLink\Api\Result::Nickname]);
            if ($this->ReadPropertyBoolean(\TpLink\Property::AutoRename) && (IPS_GetName($this->InstanceID) != $Name) && ($Name != '')) {
                IPS_SetName($this->InstanceID, $Name);
            }
            return $Result;
        }
        protected function SetStatus($Status)
        {
            if ($Status != IS_ACTIVE) {
                $this->InitBuffers();
            }
            if ($this->GetStatus() != $Status) {
                parent::SetStatus($Status);
            }
        }

        protected function SendRequest(string $Request): string
        {
            if ($this->GetStatus() != IS_ACTIVE) {
                if ($this->ReadPropertyBoolean(\TpLink\Property::Open)) {
                    if (!$this->Init()) {
                        trigger_error($this->Translate('Error on reconnect'), E_USER_NOTICE);
                        $this->SetStatus(IS_EBASE + 1);
                        return '';
                    }
                } else {
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
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
        protected function CurlDebug(int $HttpCode): void
        {
            switch ($HttpCode) {
                case 0:
                    $this->SendDebug('Not connected', '', 0);
                    break;
                case  400:
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
            $Result = $this->Handshake();
            if ($Result === true) {
                if ($this->Login()) {
                    $this->SetStatus(IS_ACTIVE);
                    return true;
                }
                return false;
            }
            if ($Result === false) {
                return false;
            }
            if ($Result === 1003) {
                if ($this->InitKlap()) {
                    if ($this->HandshakeKlap()) {
                        $this->SetStatus(IS_ACTIVE);
                        return true;
                    }
                }
                return false;
            }
            trigger_error(\TpLink\Api\Protocol::$ErrorCodes[$Result], E_USER_NOTICE);
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
            return  $Result !== false;
        }

        private function KlapEncryptedRequest(string $Payload): string
        {
            if ($this->KlapLocalSeed === '') {
                if (!$this->Init()) {
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
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
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
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
                trigger_error($msg, E_USER_NOTICE);
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
            $encryptedKey = $json[\TpLink\Api\Result::Result][\TpLink\Api\Result::EncryptedKey];
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
            $json = json_decode($tp_link_cipher->decrypt(json_decode($Result, true)[\TpLink\Api\Result::Result][\TpLink\Api\Result::Response]), true);
            $this->SendDebug(__FUNCTION__ . ' Result', $json, 0);
            if ($json[\TpLink\Api\ErrorCode] == 0) {
                $this->token = $json[\TpLink\Api\Result::Result]['token'];
                return true;
            }
            trigger_error(\TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]], E_USER_NOTICE);
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
                trigger_error($msg, E_USER_NOTICE);
                return '';
            }
            return $tp_link_cipher->decrypt($json[\TpLink\Api\Result::Result][\TpLink\Api\Result::Response]);
        }
    }
}

