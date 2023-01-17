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
 * @version       1.00
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
         parent::ApplyChanges();
//         $this->RegisterVariableBoolean('State', $this->Translate('State'), '~Switch');
     }

     public function RequestState()
     {
         if (parent::RequestState()) {
             $Result = $this->GetEnergyUsage();
             if (is_array($Result)) {
                 //$this->SetValue('State', $Result['device_on']);
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
