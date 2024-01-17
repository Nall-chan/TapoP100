<?php

declare(strict_types=1);

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
        // Connection
        public const Handshake = 'handshake';
        public const Login = 'login_device';
        public const SecurePassthrough = 'securePassthrough';
        public const MultipleRequest = 'multipleRequest';

        // Get/Set Values
        public const GetDeviceInfo = 'get_device_info';
        public const SetDeviceInfo = 'set_device_info';

        // Get/Set Time
        public const GetDeviceTime = 'get_device_time';
        public const SetDeviceTime = 'set_device_time';

        // Get Energy Values
        public const GetCurrentPower = 'get_current_power';
        public const GetEnergyUsage = 'get_energy_usage';

        // not used (now)
        public const GetDeviceUsage = 'get_device_usage';
        public const SetLightingEffect = 'set_lighting_effect';

        // not working :(
        //public const Reboot = 'reboot';
        //public const SetRelayState = 'set_relay_state'; // 'state' => int
        //public const SetLedOff = 'set_led_off'; //array 'off'=>int
        //public const GetLightState = 'get_light_state';

        // Control Child
        public const GetChildDeviceList = 'get_child_device_list';
        public const GetChildDeviceComponentList = 'get_child_device_component_list';
        public const ControlChild = 'control_child';

        //get_child_device_component_list

        public const CountdownRule = 'add_countdown_rule'; // todo wie löschen?
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
        public const DeviceID = 'device_id';
        public const MGT = 'mgt_encrypt_schm';
        public const Protocol = 'encrypt_type';
        public const ChildList = 'child_device_list';
        public const Position = 'position';
        public const SlotNumber = 'slot_number';
        public const ResponseData = 'responseData';
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
            0     => 'Success',
            -1010 => 'Invalid Public Key Length',
            -1501 => 'Invalid Request or Credentials',
            1002  => 'Incorrect Request',
            1003  => 'Invalid Protocol',
            -1001 => 'Invalid Params',
            -1002 => 'Incorrect Request',
            -1003 => 'JSON formatting error',
            -1008 => 'Value out of range',
            -1901 => 'Rule already set', //todo translate
            9999  => 'Session Timeout'
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

        public static function BuildRequest(string $Method, string $TerminalUUID = '', array $Params = []): array
        {
            $Request = [
                self::Method          => $Method,
                self::requestTimeMils => 0 //(round(time() * 1000))
            ];
            if ($TerminalUUID) {
                $Request[self::TerminalUUID] = $TerminalUUID;
            }
            if (count($Params)) {
                $Request[self::Params] = $Params;
                //$Request[self::requestTimeMils] = 0;
            }

            return $Request;
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
        public const PlugP300 = 'P300';
        public const BulbL530 = 'L530';
        public const BulbL610 = 'L610';
        public const KH100 = 'KH100';
        public const H100 = 'H100';
    }

    class GUID
    {
        public const Plug = '{AAD6F48D-C23F-4C59-8049-A9746DEB699B}';
        public const PlugEnergy = '{B18B6CAA-AB46-495D-9A7A-85FA3A83113A}';
        public const PlugsMulti = '{C923F554-4621-446E-B0D2-1422F2EB84B5}';
        public const BulbL530 = '{3C59DCC3-4441-4E1C-A59C-9F8D26CE2E82}';
        public const BulbL610 = '{1B9D73D6-853D-4E2E-9755-2273FD7A6123}';
        //public const KH100 = '{1EDD1EB2-6885-4D87-BA00-9328D74A85C4}';

        public static $TapoDevices = [
            DeviceModel::PlugP100 => self::Plug,
            DeviceModel::PlugP110 => self::PlugEnergy,
            DeviceModel::PlugP300 => self::PlugsMulti,
            DeviceModel::BulbL530 => self::BulbL530,
            DeviceModel::BulbL610 => self::BulbL610,
            DeviceModel::KH100    => self::PlugsMulti,
            DeviceModel::H100    => self::PlugsMulti,
        ];

        public static function GetByModel(string $Model)
        {
            $Model = explode(' ', $Model)[0];
            $Model = explode('(', $Model)[0];
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
        public const on_time = 'on_time';
        public const on_time_string = 'on_time_string';
        //public const auto_off_status = 'auto_off_status';
        //public const auto_off_remain_time = 'auto_off_remain_time';
        public const rssi = 'rssi'; //todo
        public const overheated = 'overheated';

        public static $Variables = [
            self::device_on=> [
                IPSVarName   => 'State',
                IPSVarType   => VARIABLETYPE_BOOLEAN,
                IPSVarProfile=> VariableProfile::Switch,
                HasAction    => true
            ],
            self::on_time=> [
                IPSVarName   => 'On time (seconds)',
                IPSVarType   => VARIABLETYPE_INTEGER,
                IPSVarProfile=> VariableProfile::RuntimeSeconds,
                HasAction    => false
            ],
            self::on_time_string=> [
                IPSVarName     => 'On time',
                IPSVarType     => VARIABLETYPE_STRING,
                IPSVarProfile  => '',
                ReceiveFunction=> 'SecondsToString',
                HasAction      => false
            ],
            /* todo
             Zeitschaltuhr zum ausschalten. Aber das ist nur der Status, Schalten fehlt noch der API Befehl :(
            self::auto_off_status=> [
                IPSVarName   => 'Auto off',
                IPSVarType   => VARIABLETYPE_STRING,
                IPSVarProfile=> '',
                SendFunction => 'SetAutOff',
                HasAction    => true
            ],
            self::auto_off_remain_time=> [
                IPSVarName   => 'Remain time to off',
                IPSVarType   => VARIABLETYPE_INTEGER,
                IPSVarProfile=> VariableProfile::UnixTimestampTime,
                HasAction    => true
            ],*/
            self::overheated=> [
                IPSVarName   => 'Overheated',
                IPSVarType   => VARIABLETYPE_BOOLEAN,
                IPSVarProfile=> '~Alert',
                HasAction    => false
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
        public const brightness = 'brightness';

        public static $Variables = [
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
        public const RuntimeSeconds = 'Tapo.RuntimeSeconds';
        public const ColorTemp = 'Tapo.ColorTemp';
        public const Brightness = 'Tapo.Brightness';
        public const Switch = '~Switch';
        public const HexColor = '~HexColor';
        public const UnixTimestampTime = '~UnixTimestampTime';
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
}

