<?php

declare(strict_types=1);
require_once __DIR__ . '/../Tapo P100/module.php';

/**
 * TapoP110 Klasse für die Anbindung von TP-Link tapo P100 / P110 Smart Sockets.
 * Erweitert TapoP100.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.10
 *
 * @example <b>Ohne</b>
 *
 * @property string $terminalUUID
 * @property string $privateKey
 * @property string $publicKey
 * @property string $token
 * @property string $cookie
 * @property string $TpLinkCipherIV
 * @property string $TpLinkCipherKey
 */
 class TapoP110 extends TapoP100
 {
     public function ApplyChanges()
     {
         //Never delete this line!
         $this->RegisterProfileInteger('Tapo.Runtime', '', '', ' minutes', 0, 0, 0);
         $this->RegisterVariableString('today_runtime', $this->Translate('Runtime today'));
         $this->RegisterVariableString('month_runtime', $this->Translate('Runtime month'));
         $this->RegisterVariableInteger('today_runtime_raw', $this->Translate('Runtime today (minutes)'), 'Tapo.Runtime');
         $this->RegisterVariableInteger('month_runtime_raw', $this->Translate('Runtime month (minutes)'), 'Tapo.Runtime');
         $this->RegisterVariableFloat('today_energy', $this->Translate('Energy today'), '~Electricity.Wh');
         $this->RegisterVariableFloat('month_energy', $this->Translate('Energy month'), '~Electricity.Wh');
         $this->RegisterVariableFloat('current_power', $this->Translate('Current power'), '~Watt');
         parent::ApplyChanges();
     }

     public function RequestState()
     {
         if (parent::RequestState()) {
             $Result = $this->GetEnergyUsage();
             if (is_array($Result)) {
                 $this->SetValue('today_runtime_raw', $Result['today_runtime']);
                 $this->SetValue('month_runtime_raw', $Result['month_runtime']);

                 $this->SetValue('today_runtime', sprintf(date('H \%\s i \%\s', $Result['today_runtime'] * 60), $this->Translate('hours'), $this->Translate('minutes')));
                 $this->SetValue('month_runtime', sprintf(date('j \%\s H \%\s i \%\s', $Result['month_runtime'] * 60), $this->Translate('days'), $this->Translate('hours'), $this->Translate('minutes')));
                 $this->SetValue('today_energy', $Result['today_energy']); //'~Electricity.Wh'
                $this->SetValue('month_energy', $Result['month_energy']); // '~Electricity.Wh'
                $this->SetValue('current_power', ($Result['current_power'] / 1000)); // '~Watt'
                return true;
             }
         }
         return false;
     }

     public function GetEnergyUsage()
     {
         $Payload = json_encode([
             'method'         => 'get_energy_usage',
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
             trigger_error(self::$ErrorCodes[$json['error_code']], E_USER_NOTICE);
             return false;
         }
         return $json['result'];
     }
 }
