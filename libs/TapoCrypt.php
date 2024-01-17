<?php

declare(strict_types=1);

namespace {
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

namespace TpLink\Crypt
{
    class Cipher
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

    trait SecurePassthroug
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
            $tp_link_cipher = new \TpLink\Crypt\Cipher($this->TpLinkCipherKey, $this->TpLinkCipherIV);
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
            $tp_link_cipher = new \TpLink\Crypt\Cipher($this->TpLinkCipherKey, $this->TpLinkCipherIV);
            $EncryptedPayload = $tp_link_cipher->encrypt($Payload);
            $SecurePassthroughPayload = \TpLink\Api\TpProtocol::BuildSecurePassthroughRequest($EncryptedPayload);
            $Result = $this->CurlRequest($Url, $SecurePassthroughPayload);
            if ($Result === false) {
                return '';
            }
            $this->SendDebug('EncryptedResponse', $Result, 0);
            $json = json_decode($Result, true);
            if ($json[\TpLink\Api\ErrorCode] == 9999) {
                // Session Timeout, try to reconnect
                $this->SendDebug('Session Timeout', '', 0);
                if (!$this->Init()) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
                    restore_error_handler();
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
            $decryptedResponse = $tp_link_cipher->decrypt($json[\TpLink\Api\Result][\TpLink\Api\Result::Response]);
            $this->SendDebug('Response', $decryptedResponse, 0);
            return $decryptedResponse;
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

    trait Klap
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
            $TpKlapCipher = new \TpLink\Crypt\KlapCipher($this->KlapLocalSeed, $this->KlapRemoteSeed, $this->KlapUserHash, $this->KlapSequenz);
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
                } else {
                    return $this->KlapEncryptedRequest($Payload);
                }
                return '';
            }
            $decryptedResponse = $TpKlapCipher->decrypt($Result);
            $this->SendDebug('Response', $decryptedResponse, 0);
            $json = json_decode($decryptedResponse, true);
            if ($json[\TpLink\Api\ErrorCode] == 9999) {
                // Session Timeout, try to reconnect
                $this->SendDebug('Session Timeout', '', 0);
                if (!$this->Init()) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Not connected'), E_USER_NOTICE);
                    restore_error_handler();
                    $this->SetStatus(IS_EBASE + 1);
                } else {
                    return $this->KlapEncryptedRequest($Payload);
                }
                return '';
            }
            return $decryptedResponse;
        }
    }
}

