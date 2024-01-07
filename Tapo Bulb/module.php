<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoLib.php';

/**
 * TapoBulb Klasse für die Anbindung von TP-Link tapo WiFi Bulbs.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.50
 *
 * @example <b>Ohne</b>
 *
 */
class TapoBulb extends \TpLink\Device
{
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function RequestAction($Ident, $Value)
    {
        $AllIdents = array_merge(\TpLink\VariableIdentBulb::$DeviceIdents, \TpLink\VariableIdent::$DefaultIdents);
        if (array_key_exists($Ident, $AllIdents)) {
            if ($AllIdents[$Ident][\TpLink\HasAction]) {
                if ($this->SetDeviceInfo([$Ident => $Value])) {
                    $this->SetValue($Ident, $Value);
                }
            }
            return;
        }
        trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
    }

    public function RequestState()
    {
        $Result = $this->GetDeviceInfo();
        if (is_array($Result)) {
            $this->SetValue(\TpLink\VariableIdent::device_on, $Result[\TpLink\VariableIdent::device_on]);
            $this->SetVariables($Result);
            return true;
        }
        return false;
    }

    protected function SetVariables(array $Values)
    {
        $AllIdents = array_merge(\TpLink\VariableIdentBulb::$DeviceIdents, \TpLink\VariableIdent::$DefaultIdents);
        foreach ($AllIdents as $Ident => $VarParams) {
            if (!array_key_exists($Ident, $Values)) {
                if (!array_key_exists(\TpLink\ReceiveFunction, $VarParams)) {
                    continue;
                }
                $Values[$Ident] = $this->{$VarParams[\TpLink\ReceiveFunction]};
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

    protected function SetDeviceInfo(array $Values)
    {
        $SendValues = [];
        $AllIdents = array_merge(\TpLink\VariableIdentBulb::$DeviceIdents, \TpLink\VariableIdent::$DefaultIdents);
        foreach ($Values as $Ident => $Value) {
            if (!array_key_exists($Ident, $AllIdents)) {
                continue;
            }

            if (array_key_exists(\TpLink\SendFunction, $AllIdents[$Ident])) {
                $SendValues = array_merge($SendValues, $this->{$AllIdents[$Ident][\TpLink\ReceiveFunction]});
                continue;
            }
            $SendValues[$Ident] = $Value;
        }
        return parent::SetDeviceInfo($SendValues);
    }

    protected function SetStatus($Status)
    {
        if ($this->GetStatus() != $Status) {
            parent::SetStatus($Status);
            if ($Status == IS_ACTIVE) {
                $this->RequestState();
            }
        }
        return true;
    }

    private function HSVtoRGB(array $Values)
    {
        $hue = $Values[\TpLink\VariableIdentBulb::hue] / 360;
        $saturation = $Values[\TpLink\VariableIdentBulb::saturation] / 100;
        $value = $Values[\TpLink\VariableIdentBulb::brightness] / 100;
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

    private function RGBtoHSV(int $RGB)
    {
        $Values[\TpLink\VariableIdentBulb::hue] = 0;
        $Values[\TpLink\VariableIdentBulb::saturation] = 0;

        $red = ($RGB >> 16) / 255;
        $green = (($RGB & 0x00FF00) >> 8) / 255;
        $blue = ($RGB & 0x0000ff) / 255;

        $min = min($red, $green, $blue);
        $max = max($red, $green, $blue);

        $value = $max;
        $delta = $max - $min;

        if ($delta == 0) {
            $Values[\TpLink\VariableIdentBulb::brightness] = (int) ($value * 100);
            return $Values;
        }

        $saturation = 0;

        if ($max != 0) {
            $saturation = ($delta / $max);
        } else {
            $Values[\TpLink\VariableIdentBulb::brightness] = (int) ($value);
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
        $Values[\TpLink\VariableIdentBulb::hue] = (int) $hue;
        $Values[\TpLink\VariableIdentBulb::saturation] = (int) ($saturation * 100);
        $Values[\TpLink\VariableIdentBulb::brightness] = (int) ($value * 100);
        return $Values;
    }
}
