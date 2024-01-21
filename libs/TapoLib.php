<?php

declare(strict_types=1);

namespace TpLink\Api
{
    const ErrorCode = 'error_code';
    const Result = 'result';

    class Method
    {
        // Connection
        public const Handshake = 'handshake';
        public const Login = 'login_device';

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
        public const Type = 'type';
        public const DeviceModel = 'device_model';
        public const Model = 'model';
        public const DeviceID = 'device_id';
        public const MGT = 'mgt_encrypt_schm';
        public const Protocol = 'encrypt_type';
        public const ChildList = 'child_device_list';
        public const Position = 'position';
        public const SlotNumber = 'slot_number';
        public const ResponseData = 'responseData';
        public const Category = 'category';
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
}

namespace TpLink\VariableIdent
{
    const OnOff = '\TpLink\VariableIdentOnOff';
    const Overheated = '\TpLink\VariableIdentOverheated';
    const Socket = '\TpLink\VariableIdentSocket';
    const Rssi = '\TpLink\VariableIdentRssi';
    const Light = '\TpLink\VariableIdentLight';
    const LightColor = '\TpLink\VariableIdentLightColor';
    const Humidity = '\TpLink\VariableIdentHumidity';
    const Online = '\TpLink\VariableIdentOnline';
    const Temp = '\TpLink\VariableIdentTemp';
    const Battery = '\TpLink\VariableIdentBattery';
    const Trv = '\TpLink\VariableIdentTrv';
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
        public const PlugP100 = 'P100'; // WLAN-Steckdose
        public const PlugP105 = 'P105'; // WLAN Steckdose Rund
        public const PlugP110 = 'P110'; // WLAN Steckdose mit Messung
        public const PlugP115 = 'P115'; // WLAN-Steckdose Rund mit Verbrauchsanzeige
        public const PlugP300 = 'P300'; // WLAN Power-Strip
        public const BulbL510 = 'L510'; // E27-Glühbirne, dimmbar
        public const BulbL520 = 'L520'; // E27-Glühbirne, dimmbar
        public const BulbL530 = 'L530'; // E27-Glühbirne, mehrfarbig
        public const BulbL535 = 'L535'; // E27-Glühbirne, mehrfarbig
        public const BulbL610 = 'L610'; // Wi-Fi Strahler GU10, dimmbar
        public const BulbL630 = 'L630'; // Wi-Fi-Strahler GU10, mehrfarbig
        public const StripeL900 = 'L900'; // Wi-Fi Light Strip RGB
        public const StripeL920 = 'L920'; // Wi-Fi Light Strip Multifarben ? Zonen
        public const StripeL930 = 'L930'; // Wi-Fi Light Strip RGBW , Multifarben 50 Zonen
        public const KH100 = 'KH100'; // Hub mit integrierter Sirene
        public const H100 = 'H100'; // Hub mit integrierter Sirene
        public const H200 = 'H200'; // Hub mit LAN

        public static $DeviceModels = [
            self::PlugP100    => GUID::Plug,
            self::PlugP105    => GUID::Plug,
            self::PlugP110    => GUID::PlugEnergy,
            self::PlugP115    => GUID::PlugEnergy,
            self::PlugP300    => GUID::PlugsMulti,
            self::BulbL510    => GUID::BulbWithe,
            self::BulbL520    => GUID::BulbWithe,
            self::BulbL530    => GUID::BulbColor,
            self::BulbL535    => GUID::BulbColor,
            self::BulbL610    => GUID::BulbWithe,
            self::BulbL630    => GUID::BulbColor,
            self::StripeL900  => GUID::BulbColor,
            self::KH100       => GUID::HubConfigurator,
            self::H100        => GUID::HubConfigurator,
            self::H200        => GUID::HubConfigurator,
        ];

        public static function GetGuidByDeviceModel(string $Model): string
        {
            $Match = [];
            if (preg_match('/^[a-zA-Z]{1,2}\d{3}/', $Model, $Match)) {
                if (array_key_exists($Match[0], self::$DeviceModels)) {
                    return self::$DeviceModels[$Match[0]];
                }
            }
            return '';
        }
    }

    class HubChildDevicesModel
    {
        public const KE100 = 'KE100'; // Heizkörperthermostat
        public const T100 = 'T100'; // Bewegungsmelder
        public const T110 = 'T110'; // Intelligenter Kontaktsensor
        public const T300 = 'T300'; // Wasserlecksensor
        public const T310 = 'T310'; // Temperatur- & Feuchtigkeitsmonitor
        public const T315 = 'T315'; // Temperatur- & Feuchtigkeitsmonitor mit Display
        public const S200 = 'S200'; // Remote Button oder Dimmschalter
        public const S210 = 'S210'; // Lichtschalter 1-fach
        public const S220 = 'S220'; // Lichtschalter 2-fach

        public static $DeviceModels = [
            self::KE100 => GUID::HubChild,
            self::T100  => GUID::HubChild,
            self::T110  => GUID::HubChild,
            self::T300  => GUID::HubChild,
            self::T310  => GUID::HubChild,
            self::T315  => GUID::HubChild,
            self::S200  => GUID::HubChild,
            self::S210  => GUID::HubChild,
            self::S220  => GUID::HubChild
        ];

        public static function GetGuidByDeviceModel(string $Model): string
        {
            $Match = [];
            if (preg_match('/^[a-zA-Z]{1,2}\d{3}/', $Model, $Match)) {
                if (array_key_exists($Match[0], self::$DeviceModels)) {
                    return self::$DeviceModels[$Match[0]];
                }
            }
            return '';
        }
    }

    class HubChildDevicesCategory
    {
        public const TRV = 'trv';
        public const TempHmdtSensor = 'temp-hmdt-sensor';

        private static $Idents = [
            self::TempHmdtSensor => [
                VariableIdent\Online,
                VariableIdent\Battery,
                VariableIdent\Humidity,
                VariableIdent\Rssi,
                VariableIdent\Temp
            ],
            self::TRV => [
                VariableIdent\Online,
                VariableIdent\Battery,
                VariableIdent\Rssi,
                VariableIdent\Temp,
                VariableIdent\Trv
            ],
        ];

        public static function GetVariableIdentsByCategory(string $Category): array
        {
            $AllIdents = [];
            if (array_key_exists($Category, self::$Idents)) {
                foreach (self::$Idents[$Category] as $VariableIdentClassName) {
                    /** @var VariableIdent $VariableIdentClassName */
                    $AllIdents = array_merge($AllIdents, $VariableIdentClassName::$Variables);
                }
            }
            return $AllIdents;
        }
    }

    class GUID
    {
        public const Plug = '{AAD6F48D-C23F-4C59-8049-A9746DEB699B}';
        public const PlugEnergy = '{B18B6CAA-AB46-495D-9A7A-85FA3A83113A}';
        public const PlugsMulti = '{C923F554-4621-446E-B0D2-1422F2EB84B5}';
        public const BulbColor = '{3C59DCC3-4441-4E1C-A59C-9F8D26CE2E82}';
        public const BulbWithe = '{1B9D73D6-853D-4E2E-9755-2273FD7A6123}';
        public const Hub = '{1EDD1EB2-6885-4D87-BA00-9328D74A85C4}';
        public const HubConfigurator = '{CA1E7005-E5D1-455C-95DF-5ECE8DC50654}';
        public const HubSendToChild = '{A982FDFB-9576-4DAE-9341-5ADCA8B05326}';
        public const ChildSendToHub = '{5377F7F9-4F55-486C-AA61-C4203190065F}';
        public const HubChild = '{DBBC5150-EF75-487B-9407-27C11DDEF6B4}';
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
        public const DeviceId = 'DeviceId';
    }

    class Attribute
    {
        public const Username = 'Username';
        public const Password = 'Password';
        public const Category = 'Category';
    }

    class Timer
    {
        public const RequestState = 'RequestState';
    }

    class VariableIdentOnOff
    {
        public const device_on = 'device_on';

        public static $Variables = [
            self::device_on=> [
                IPSVarName   => 'State',
                IPSVarType   => VARIABLETYPE_BOOLEAN,
                IPSVarProfile=> VariableProfile::Switch,
                HasAction    => true
            ]
        ];
    }

    class VariableIdentOverheated
    {
        public const overheated = 'overheated';

        public static $Variables = [
            self::overheated=> [
                IPSVarName   => 'Overheated',
                IPSVarType   => VARIABLETYPE_BOOLEAN,
                IPSVarProfile=> '~Alert',
                HasAction    => false
            ]
        ];
    }

    class VariableIdentSocket
    {
        public const on_time = 'on_time';
        public const on_time_string = 'on_time_string';
        //public const auto_off_status = 'auto_off_status';
        //public const auto_off_remain_time = 'auto_off_remain_time';

        public static $Variables = [
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
        ];
    }

    class VariableIdentRssi
    {
        public const rssi = 'rssi';

        public static $Variables = [
            self::rssi => [
                IPSVarName              => 'Rssi',
                IPSVarType              => VARIABLETYPE_INTEGER,
                IPSVarProfile           => '',
                HasAction               => false
            ]
        ];
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

    class VariableIdentOnline
    {
        public const status = 'status';

        public static $Variables = [
            self::status => [
                IPSVarName              => 'Online status',
                IPSVarType              => VARIABLETYPE_STRING,
                IPSVarProfile           => '',
                HasAction               => false
            ]
        ];
    }

    class VariableIdentHumidity
    {
        public const current_humidity = 'current_humidity';

        public static $Variables = [
            self::current_humidity=> [
                IPSVarName   => 'Current humidity',
                IPSVarType   => VARIABLETYPE_INTEGER,
                IPSVarProfile=> VariableProfile::Humidity,
                HasAction    => false
            ]
        ];
    }

    class VariableIdentTemp
    {
        public const current_temp = 'current_temp';

        public static $Variables = [
            self::current_temp=> [
                IPSVarName   => 'Current temperature',
                IPSVarType   => VARIABLETYPE_FLOAT,
                IPSVarProfile=> VariableProfile::Temperature,
                HasAction    => false
            ]
        ];
    }

    class VariableIdentBattery
    {
        public const at_low_battery = 'at_low_battery';
        public const battery_percentage = 'battery_percentage';

        public static $Variables = [
            self::at_low_battery=> [
                IPSVarName   => 'Low battery',
                IPSVarType   => VARIABLETYPE_BOOLEAN,
                IPSVarProfile=> VariableProfile::BatteryLow,
                HasAction    => false
            ],
            self::battery_percentage=> [
                IPSVarName   => 'Battery',
                IPSVarType   => VARIABLETYPE_INTEGER,
                IPSVarProfile=> VariableProfile::Battery,
                HasAction    => false
            ]
        ];
    }

    class VariableIdentTrv
    {
        public const target_temp = 'target_temp';
        public const frost_protection_on = 'frost_protection_on';
        public const child_protection = 'child_protection';
        public const trv_states = 'trv_states';

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
            self::trv_states=> [
                IPSVarName     => 'State',
                IPSVarType     => VARIABLETYPE_STRING,
                IPSVarProfile  => '',
                HasAction      => false,
                ReceiveFunction=> 'TrvStateToString',
            ],

        ];
    }
    /*
        // channel group alarm
        public static final String CHANNEL_GROUP_ALARM = "alarm";
        public static final String CHANNEL_ALARM_ACTIVE = "alarmActive";
        public static final String CHANNEL_ALARM_SOURCE = "alarmSource";
        public static final String CHANNEL_GROUP_SENSOR = "sensor";
        public static final String CHANNEL_IS_OPEN = "isOpen";
        public static final String CHANNEL_TEMPERATURE = "currentTemp";
        public static final String CHANNEL_HUMIDITY = "currentHumidity";
        // hub child events
        public static final String EVENT_BATTERY_LOW = "batteryIsLow";
        public static final String EVENT_CONTACT_OPENED = "contactOpened";
        public static final String EVENT_CONTACT_CLOSED = "contactClosed";
        public static final String EVENT_STATE_BATTERY_LOW = "batteryLow";
        public static final String EVENT_STATE_OPENED = "open";
        public static final String EVENT_STATE_CLOSED = "closed";
     */
    class VariableProfile
    {
        public const Runtime = 'Tapo.Runtime';
        public const RuntimeSeconds = 'Tapo.RuntimeSeconds';
        public const ColorTemp = 'Tapo.ColorTemp';
        public const Brightness = 'Tapo.Brightness';
        public const Switch = '~Switch';
        public const HexColor = '~HexColor';
        public const UnixTimestampTime = '~UnixTimestampTime';
        public const TargetTemperature = 'Tapo.Temperature.Room';
        public const Temperature = '~Temperature';
        public const Humidity = '~Humidity';
        public const Battery = '~Battery.100';
        public const BatteryLow = '~Battery';
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

        public static function ToRGB(int $Kelvin): array
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

