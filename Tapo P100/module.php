<?php

declare(strict_types=1);

namespace {
eval('declare(strict_types=1);namespace TapoP100 {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace TapoP100 {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace TapoP100 {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace TapoP100 {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

$AutoLoader = new AutoLoaderTapoP100PHPSecLib('Crypt/Random');
$AutoLoader->register();

    /**
     * @property string $terminalUUID
     * @property string $privateKey
     * @property string $publicKey
     * @property string $token
     * @property string $cookie
     * @property string $TpLinkCipherIV
     * @property string $TpLinkCipherKey
     */
    class TapoP100 extends IPSModule
    {
        use \TapoP100\BufferHelper;
        use \TapoP100\DebugHelper;
        use \TapoP100\Semaphore;
        use \TapoP100\VariableProfileHelper;

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyBoolean('Open', false);
            $this->RegisterPropertyString('Host', '');
            $this->RegisterPropertyString('Username', '');
            $this->RegisterPropertyString('Password', '');
            $this->RegisterPropertyInteger('Interval', 5);
            $this->RegisterPropertyBoolean('AutoRename', true);
            $this->RegisterTimer('RequestState', 0, 'P100_RequestState($_IPS[\'TARGET\']);');
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            $this->SetTimerInterval('RequestState', 0);
            //Never delete this line!
            parent::ApplyChanges();
            $this->RegisterVariableBoolean('State', $this->Translate('State'), '~Switch');
            $this->EnableAction('State');
            $this->terminalUUID = $this->guidv4(\phpseclib\Crypt\Random::string(16));
            $Key = (new \phpseclib\Crypt\RSA())->createKey(1024);
            $this->privateKey = $Key['privatekey'];
            $this->publicKey = $Key['publickey'];
            $this->token = '';
            $this->cookie = '';

            if ($this->ReadPropertyBoolean('Open')) {
                if ($this->ReadPropertyString('Host') != '') {
                    if (@Sys_Ping($this->ReadPropertyString('Host'), 1000)) {
                        if ($this->Init()) {
                            $this->SetStatus(IS_ACTIVE);
                            $this->RequestState();
                            $this->SetTimerInterval('RequestState', $this->ReadPropertyInteger('Interval') * 1000);
                            return;
                        }
                    }
                }
                $this->SetStatus(IS_EBASE + 1);
            } else {
                $this->SetStatus(IS_INACTIVE);
            }
            $this->token = '';
            $this->cookie = '';
        }

        public function RequestAction($Ident, $Value)
        {
            switch ($Ident) {
                case 'State':
                    return $this->SwitchMode($Value);
            }
        }

        public function RequestState()
        {
            $Result = $this->GetDeviceInfo();
            if (is_array($Result)) {
                $this->SetValue('State', $Result['device_on']);
                return true;
            }
            return false;
        }

        public function GetDeviceInfo()
        {
            $Payload = json_encode([
                'method'         => 'get_device_info',
                'requestTimeMils'=> 0
            ]);
            $this->SendDebug(__FUNCTION__, $Payload, 0);
            $decryptedResponse = $this->EncryptedRequest($Payload);
            $this->SendDebug(__FUNCTION__ . ' Result', $decryptedResponse, 0);
            if ($decryptedResponse === '') {
                return [];
            }
            $json = json_decode($decryptedResponse, true);
            if ($json['error_code'] != 0) {
                trigger_error('error_code:' . $json['error_code'], E_USER_NOTICE);
                return false;
            }

            $Name = base64_decode($json['result']['nickname']);
            if ($this->ReadPropertyBoolean('AutoRename') && (IPS_GetName($this->InstanceID)) != $Name) {
                IPS_SetName($this->InstanceID, $Name);
            }

            return $json['result'];
        }

        public function SwitchMode(bool $State): bool
        {
            $Payload = json_encode([
                'method'=> 'set_device_info',
                'params'=> [
                    'device_on'=> $State
                ],
                'requestTimeMils'=> 0,
                'terminalUUID'   => $this->terminalUUID
            ]);
            $this->SendDebug(__FUNCTION__, $Payload, 0);
            $decryptedResponse = $this->EncryptedRequest($Payload);
            $this->SendDebug(__FUNCTION__ . ' Result', $decryptedResponse, 0);
            if ($decryptedResponse === '') {
                return false;
            }
            $json = json_decode($decryptedResponse, true);
            if ($json['error_code'] != 0) {
                trigger_error('error_code:' . $json['error_code'], E_USER_NOTICE);
                return false;
            }
            $this->SetValue('State', $State);
            return true;
        }

        public function SwitchModeEx(bool $State, int $Delay): bool
        {
            $Payload = json_encode([
                'method'=> 'add_countdown_rule',
                'params'=> [
                    'delay'         => $Delay,
                    'desired_states'=> [
                        'on' => $State
                    ],
                    'enable'   => true,
                    'remain'   => $Delay
                ],
                'terminalUUID'   => $this->terminalUUID
            ]);
            $this->SendDebug(__FUNCTION__, $Payload, 0);
            $decryptedResponse = $this->EncryptedRequest($Payload);
            $this->SendDebug(__FUNCTION__ . ' Result', $decryptedResponse, 0);
            if ($decryptedResponse === '') {
                return false;
            }
            $json = json_decode($decryptedResponse, true);
            if ($json['error_code'] != 0) {
                trigger_error('error_code:' . $json['error_code'], E_USER_NOTICE);
                return false;
            }
            $this->SetValue('State', $State);
            return true;
        }

        private function Init(): bool
        {
            $Bytes = $this->Handshake();
            $Data = str_split($Bytes, 16);
            $this->TpLinkCipherKey = $Data[0];
            $this->TpLinkCipherIV = $Data[1];
            $token = $this->Login();
            if ($token == '') {
                return false;
            }
            $this->token = $token;
            return true;
        }

        private function Login(): string
        {
            $Url = 'http://' . $this->ReadPropertyString('Host') . '/app';
            $Payload = json_encode([
                'method'=> 'login_device',
                'params'=> [
                    'password'=> base64_encode($this->ReadPropertyString('Password')),
                    'username'=> base64_encode(sha1($this->ReadPropertyString('Username')))
                ],
                'requestTimeMils'=> 0

            ]);
            $this->SendDebug(__FUNCTION__, $Payload, 0);

            $tp_link_cipher = new \TapoP100\TpLinkCipher($this->TpLinkCipherKey, $this->TpLinkCipherIV);
            $EncryptedPayload = $tp_link_cipher->encrypt($Payload);
            $SecurePassthroughPayload = json_encode([
                'method'=> 'securePassthrough',
                'params'=> [
                    'request'=> $EncryptedPayload
                ]]);
            $Result = $this->CurlRequest($Url, $SecurePassthroughPayload);
            if ($Result === '') {
                return '';
            }

            $json = json_decode($tp_link_cipher->decrypt(json_decode($Result, true)['result']['response']), true);
            $this->SendDebug(__FUNCTION__ . ' Result', $json, 0);
            if ($json['error_code'] == 0) {
                return $json['result']['token'];
            }
            trigger_error('error_code:' . $json['error_code'], E_USER_NOTICE);
            return '';
        }

        private function EncryptedRequest(string $Payload): string
        {
            if ($this->token === '') {
                return '';
            }
            $Url = 'http://' . $this->ReadPropertyString('Host') . '/app?token=' . $this->token;
            $tp_link_cipher = new \TapoP100\TpLinkCipher($this->TpLinkCipherKey, $this->TpLinkCipherIV);
            $EncryptedPayload = $tp_link_cipher->encrypt($Payload);
            $SecurePassthroughPayload = json_encode([
                'method'=> 'securePassthrough',
                'params'=> [
                    'request'=> $EncryptedPayload
                ]]);
            $Result = $this->CurlRequest($Url, $SecurePassthroughPayload);
            if ($Result === '') {
                return '';
            }
            return $tp_link_cipher->decrypt(json_decode($Result, true)['result']['response']);
        }

        private function CurlRequest(string $Url, string $Payload): string
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
            if (is_bool($Result)) {
                $Result = '';
            }
            curl_close($ch);
            if ($HttpCode == 0) {
                $this->SendDebug('Not connected', '', 0);
                return '';
            } elseif ($HttpCode == 400) {
                $this->SendDebug('Bad Request', $HttpCode, 0);
                return '';
            } elseif ($HttpCode == 401) {
                $this->SendDebug('Unauthorized Error', $HttpCode, 0);
                return '';
            } elseif ($HttpCode == 404) {
                $this->SendDebug('Not Found Error', $HttpCode, 0);
                return '';
            }
            return $Result;
        }

        private function Handshake(): string
        {
            $Url = 'http://' . $this->ReadPropertyString('Host') . '/app';
            $Payload = json_encode([
                'method'=> 'handshake',
                'params'=> [
                    'key'            => utf8_decode($this->publicKey),
                    'requestTimeMils'=> 0
                ]

            ]);

            $this->SendDebug('Handshake', $Payload, 0);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $Url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $Payload);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
            curl_setopt($ch, CURLOPT_COOKIEFILE, '');
            $Result = curl_exec($ch);
            $HttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->cookie = curl_getinfo($ch, CURLINFO_COOKIELIST)[0];
            if (is_bool($Result)) {
                $Result = '';
            }
            curl_close($ch);
            if ($HttpCode == 0) {
                $this->SendDebug('Not connected', '', 0);
                return '';
            } elseif ($HttpCode == 400) {
                $this->SendDebug('Bad Request', $HttpCode, 0);
                return '';
            } elseif ($HttpCode == 401) {
                $this->SendDebug('Unauthorized Error', $HttpCode, 0);
                return '';
            } elseif ($HttpCode == 404) {
                $this->SendDebug('Not Found Error', $HttpCode, 0);
                return '';
            } else {
                $this->SendDebug('Handshake Result:' . $HttpCode, $Result, 0);
            }
            $encryptedKey = json_decode($Result, true)['result']['key'];

            $ciphertext = base64_decode($encryptedKey);
            $rsa = new \phpseclib\Crypt\RSA();
            $rsa->loadKey($this->privateKey);
            return $rsa->_rsaes_pkcs1_v1_5_decrypt($ciphertext);
        }

        private function guidv4($data = null): string
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

namespace TapoP100 {

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
            $pad_text = $cipher->decrypt(base64_decode($data));
            return $pad_text;
        }
    }
}
