<?php

declare(strict_types=1);

namespace {
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

        public static function GetUrlWithToken(string $Host, string $Token): string
        {
            return Protocol . $Host . Url::App . '?' . http_build_query([self::Token => $Token]);
        }

                public static function BuildSecurePassthroughRequest(string $EncryptedPayload): string
                {
                    return json_encode([
                        Protocol::Method=> Method::SecurePassthrough,
                        Protocol::Params=> [
                            Protocol::Params=> $EncryptedPayload
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
}