<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoDevice.php';

/**
 * TapoLightlb Klasse für die Anbindung von TP-Link tapo WiFi Bulbs & Strips.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.60
 */
class TapoLightColor extends \TpLink\Device
{
    protected static $ModuleIdents = [
        \TpLink\VariableIdent\OnOff,
        \TpLink\VariableIdent\Overheated,
        \TpLink\VariableIdent\Rssi,
        \TpLink\VariableIdent\Light,
        \TpLink\VariableIdent\LightColor
    ];

    public function ApplyChanges()
    {
        $this->RegisterProfileInteger(\TpLink\VariableProfile::Brightness, 'Intensity', '', '%', 1, 100, 1);
        $this->RegisterProfileInteger(\TpLink\VariableProfile::ColorTemp, '', '', ' K', 2500, 6500, 1);
        //Never delete this line!
        parent::ApplyChanges();
    }

    /**
     * HSVtoRGB ReceiveFunction
     *
     * not static, falls wir doch auf Statusvariablen zurückgreifen müssen
     *
     * @param  array $Values
     * @return int
     */
    protected function HSVtoRGB(array $Values)
    {
        $color_temp = $Values[\TpLink\VariableIdentLightColor::color_temp];
        if ($color_temp > 0) {
            list($red, $green, $blue) = \TpLink\KelvinTable::ToRGB($color_temp);
            return ($red << 16) ^ ($green << 8) ^ $blue;
        }
        $hue = $Values[\TpLink\VariableIdentLightColor::hue] / 360;
        $saturation = $Values[\TpLink\VariableIdentLightColor::saturation] / 100;
        $value = $Values[\TpLink\VariableIdentLightColor::brightness] / 100;
        if ($saturation == 0) {
            $red = $value * 255;
            $green = $value * 255;
            $blue = $value * 255;
        } else {
            $var_h = $hue * 6;
            $var_i = floor($var_h);
            $var_1 = $value * (1 - $saturation);
            $var_2 = $value * (1 - $saturation * ($var_h - $var_i));
            $var_3 = $value * (1 - $saturation * (1 - ($var_h - $var_i)));

            switch ($var_i) {
                case 0:
                    $var_r = $value;
                    $var_g = $var_3;
                    $var_b = $var_1;
                    break;
                case 1:
                    $var_r = $var_2;
                    $var_g = $value;
                    $var_b = $var_1;
                    break;
                case 2:
                    $var_r = $var_1;
                    $var_g = $value;
                    $var_b = $var_3;
                    break;
                case 3:
                    $var_r = $var_1;
                    $var_g = $var_2;
                    $var_b = $value;
                    break;
                case 4:
                    $var_r = $var_3;
                    $var_g = $var_1;
                    $var_b = $value;
                    break;
                default:
                    $var_r = $value;
                    $var_g = $var_1;
                    $var_b = $var_2;
                    break;
            }

            $red = (int) round($var_r * 255);
            $green = (int) round($var_g * 255);
            $blue = (int) round($var_b * 255);
        }

        return ($red << 16) ^ ($green << 8) ^ $blue;
    }

    /**
     * RGBtoHSV SendFunction
     *
     * not static, falls wir doch auf Statusvariablen zurückgreifen müssen
     *
     * @param  int $RGB
     * @return array
     */
    protected function RGBtoHSV(int $RGB)
    {
        $Values[\TpLink\VariableIdentLightColor::color_temp] = 0;
        $Values[\TpLink\VariableIdentLightColor::hue] = 0;
        $Values[\TpLink\VariableIdentLightColor::saturation] = 0;

        $red = ($RGB >> 16) / 255;
        $green = (($RGB & 0x00FF00) >> 8) / 255;
        $blue = ($RGB & 0x0000ff) / 255;

        $min = min($red, $green, $blue);
        $max = max($red, $green, $blue);

        $value = $max;
        $delta = $max - $min;

        if ($delta == 0) {
            $Values[\TpLink\VariableIdentLightColor::brightness] = (int) ($value * 100);
            return $Values;
        }

        $saturation = 0;

        if ($max != 0) {
            $saturation = ($delta / $max);
        } else {
            $Values[\TpLink\VariableIdentLightColor::brightness] = (int) ($value);
            return $Values;
        }
        if ($red == $max) {
            $hue = ($green - $blue) / $delta;
        } else {
            if ($green == $max) {
                $hue = 2 + ($blue - $red) / $delta;
            } else {
                $hue = 4 + ($red - $green) / $delta;
            }
        }
        $hue *= 60;
        if ($hue < 0) {
            $hue += 360;
        }
        $Values[\TpLink\VariableIdentLightColor::hue] = (int) $hue;
        $Values[\TpLink\VariableIdentLightColor::saturation] = (int) ($saturation * 100);
        $Values[\TpLink\VariableIdentLightColor::brightness] = (int) ($value * 100);
        return $Values;
    }
}
