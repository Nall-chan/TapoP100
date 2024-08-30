<?php

declare(strict_types=1);

use const TpLink\IPSVarProfile;
use const TpLink\IPSVarType;

require_once dirname(__DIR__) . '/Tapo Light Color/module.php';

/**
 * TapoStripeColor Klasse für die Anbindung von mehrfarbigen WiFi Bulbs & Strips.
 * Erweitert TapoLightColor.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.70
 *
 * @property array $LastEffect
 */
class TapoStripeColor extends TapoLightColor
{
    protected static $ModuleIdents = [
        \TpLink\VariableIdent\OnOff,
        \TpLink\VariableIdent\Overheated,
        \TpLink\VariableIdent\Rssi,
        \TpLink\VariableIdent\Light,
        \TpLink\VariableIdent\LightColor,
        \TpLink\VariableIdent\LightEffect
    ];

    public function Create()
    {
        parent::Create();
        $Effects = json_decode(file_get_contents(dirname(__DIR__) . '/libs/effects.json'), true);
        $EffectsProperty = [];
        foreach ($Effects as $Effect) {
            $Item = array_intersect_key($Effect, array_flip(['id', 'name', 'enable']));
            $Item['name'] = $this->Translate($Item['name']);
            $EffectsProperty[] = $Item;
        }
        $this->RegisterPropertyString(\TpLink\Property::LightEffectsEnabled, json_encode($EffectsProperty));
        //$this->RegisterAttributeArray(\TpLink\Attribute::LightEffectsEnabled, json_encode($EffectsProperty));
        $this->RegisterAttributeArray(\TpLink\Attribute::LightEffects, $Effects, 5);
        $this->LastEffect = [];
    }

    public function ApplyChanges()
    {
        $OldEffects = $this->ReadAttributeArray(\TpLink\Attribute::LightEffects);
        $NewEffects = [];
        foreach (json_decode($this->ReadPropertyString(\TpLink\Property::LightEffectsEnabled), true) as $Effect) {
            if ($Effect['enable']) {
                $ProfileEffects[] = [$Effect['id'], $Effect['name'], '', -1];
            }
            $Index = array_search($Effect['id'], array_column($OldEffects, 'id'));
            if ($Index !== false) {
                $NewEffects[] = $OldEffects[$Index];
            }
        }
        $this->WriteAttributeArray(\TpLink\Attribute::LightEffects, $NewEffects);
        $EffectsProfileName = sprintf(\TpLink\VariableIdentLightEffect::$Variables[\TpLink\VariableIdentLightEffect::lighting_effect][IPSVarProfile], $this->InstanceID);
        $this->UnregisterProfile($EffectsProfileName);
        $this->SendDebug('profile', $ProfileEffects, 0);
        $this->RegisterProfileStringEx($EffectsProfileName, '', '', '', $ProfileEffects);
        $this->LastEffect = [];
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(parent::GetConfigurationForm(), true);
        $AddElements = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $LightEffectsEnabled = json_decode($this->ReadPropertyString(\TpLink\Property::LightEffectsEnabled), true);
        $KnownEffects = $this->ReadAttributeArray(\TpLink\Attribute::LightEffects);
        foreach ($KnownEffects as $Effect) {
            $Index = array_search($Effect['id'], array_column($LightEffectsEnabled, 'id'));
            if ($Index === false) {
                $LightEffectsEnabled[] = ['id'=> $Effect['id'], 'name' => $Effect['name'], 'enable'=>true];
            }
        }
        $AddElements['elements'][0]['values'] = $LightEffectsEnabled;
        $Form['elements'] = array_merge(
            $Form['elements'],
            $AddElements['elements']
        );
        $Form['actions'] = array_merge($AddElements['actions'], $Form['actions']);
        $Form['translations']['de'] = array_merge($AddElements['translations']['de'], $Form['translations']['de']);
        return json_encode($Form);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == 'ClearEffects') {
            $this->ClearEffects();
            return;
        }
        parent::RequestAction($Ident, $Value);
    }

    public function Test(string $Value)
    {
        $Request = \TpLink\Api\Protocol::BuildRequest($Value);
        $Response = $this->SendRequest($Request);
        if ($Response === null) {
            return false;
        }
        return $Response;
    }
    public function Test2(string $Value, string $Value2)
    {
        $Request = \TpLink\Api\Protocol::BuildRequest($Value, '', json_decode($Value2, true));
        $Response = $this->SendRequest($Request);
        if ($Response === null) {
            return false;
        }
        return $Response;
    }

    /**
     * LightEffectToVariable ReceiveFunction
     *
     * @param  array $Values
     * @return string VariableValue id for lighting_effect
     */
    protected function LightEffectToVariable(array $Values)
    {
        $enabled = $Values[\TpLink\VariableIdentLightEffect::lighting_effect]['enable'] ?? 0;
        $effectId = '';
        if ($enabled) {
            $effectId = $Values['default_states']['state'][\TpLink\VariableIdentLightEffect::lighting_effect]['id'] ?? '';
        } else {
            $this->LastEffect = [];
        }
        /*$enabled = $Values['segment_effect']['enable'] ?? 0;
        if ($enabled){
            return $Values['default_states']['state']['segment_effect']['id'] ?? '';
        }*/
        $UpdateEffect = true;
        if (@$this->GetIDForIdent(\TpLink\VariableIdentLightEffect::lighting_effect)) {
            if ($this->GetValue(\TpLink\VariableIdentLightEffect::lighting_effect) == $effectId) {
                $UpdateEffect = false;
            }
        }
        if ($UpdateEffect) {
            if ($enabled) {
                $this->LastEffect = $Values['default_states']['state'][\TpLink\VariableIdentLightEffect::lighting_effect];
                if ($effectId) {
                    $this->UpdateEffectById($effectId, $Values['default_states']['state'][\TpLink\VariableIdentLightEffect::lighting_effect]);
                }
            }
            return $effectId;
        }
        return null;
    }

    /**
     * BrightnessToVariable ReceiveFunction
     *
     * @param  array $Values
     * @return int Brightness
     */
    protected function BrightnessToVariable(array $Values)
    {
        $enabled = $Values[\TpLink\VariableIdentLightEffect::lighting_effect]['enable'] ?? 0;
        if ($enabled) {
            return $Values['default_states']['state'][\TpLink\VariableIdentLightEffect::lighting_effect][\TpLink\VariableIdentLight::brightness] ?? 0;
        } else {
            return $Values[\TpLink\VariableIdentLight::brightness];
        }
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
        $enabled = $Values[\TpLink\VariableIdentLightEffect::lighting_effect]['enable'] ?? 0;
        if ($enabled) {
            $Values[\TpLink\VariableIdentLightColor::hue] = $Values[\TpLink\VariableIdentLightEffect::lighting_effect]['display_colors'][0][0];
            $Values[\TpLink\VariableIdentLightColor::saturation] = $Values[\TpLink\VariableIdentLightEffect::lighting_effect]['display_colors'][0][1];
            $Values[\TpLink\VariableIdentLight::brightness] = $Values[\TpLink\VariableIdentLightEffect::lighting_effect]['display_colors'][0][2];
        }

        return parent::HSVtoRGB($Values);
    }
    /**
     * ActivateLightEffect SendFunction
     *
     * @param  string $id lighting_effect value (id)
     * @return array all values
     */
    protected function ActivateLightEffect(string $id)
    {
        $Effect = $this->GetEffectById($id);
        if (!count($Effect)) {
            return false;
        }
        $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::SetLightingEffect, '', $Effect);
        $Response = $this->SendRequest($Request);
        if ($Response === null) {
            return false;
        }
        $this->LastEffect = $Effect;
        $this->SetValue(\TpLink\VariableIdentLightEffect::lighting_effect, $id);
        return true;
    }

    /**
     * SendBrightness SendFunction
     * @return array
     */
    protected function SendBrightness(int $Brightness)
    {
        $Effect = $this->LastEffect;
        if (count($Effect)) {
            $Effect[\TpLink\VariableIdentLight::brightness] = $Brightness;
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::SetLightingEffect, '', $Effect);
            $Response = $this->SendRequest($Request);
            if ($Response === null) {
                return false;
            }
            $this->SetValue(\TpLink\VariableIdentLight::brightness, $Brightness);
            return true;
        }
        return [\TpLink\VariableIdentLight::brightness => $Brightness];
    }

    private function GetEffectById(string $id)
    {
        $Effects = $this->ReadAttributeArray(\TpLink\Attribute::LightEffects);
        $Index = array_search($id, array_column($Effects, 'id'));
        if ($Index === false) {
            return [];
        }
        return $Effects[$Index];
    }

    private function UpdateEffectById(string $id, array $Effect)
    {
        $Effects = $this->ReadAttributeArray(\TpLink\Attribute::LightEffects);
        $Index = array_search($id, array_column($Effects, 'id'));
        if ($Index === false) {
            $Effects[] = $Effect;
        } else {
            $Effects[$Index] = $Effect;
        }
        $this->WriteAttributeArray(\TpLink\Attribute::LightEffects, $Effects);
        $EffectsProfileName = sprintf(\TpLink\VariableIdentLightEffect::$Variables[\TpLink\VariableIdentLightEffect::lighting_effect][IPSVarProfile], $this->InstanceID);
        IPS_SetVariableProfileAssociation($EffectsProfileName, $id, $Effect['name'], '', -1);

    }

    private function ClearEffects()
    {
        $Effects = json_decode(file_get_contents(dirname(__DIR__) . '/libs/effects.json'), true);
        $EffectsProperty = [];
        foreach ($Effects as $Effect) {
            $Item = array_intersect_key($Effect, array_flip(['id', 'name', 'enable']));
            $Item['name'] = $this->Translate($Item['name']);
            $EffectsProperty[] = $Item;
        }
        $this->UpdateFormField('LightEffectsEnabled', 'values', json_encode($EffectsProperty));
        $this->WriteAttributeArray(\TpLink\Attribute::LightEffects, $Effects);
    }
}
