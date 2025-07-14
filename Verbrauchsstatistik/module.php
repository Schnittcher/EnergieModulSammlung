<?php

declare(strict_types=1);
define('PREFIX', 'SAW');

define('LOD_DATE', 0);
define('LOD_TIME', 1);
define('LOD_DATETIME', 2);

    class Verbrauchsstatistik extends IPSModule
    {
        public function Create()
        {
            //Never delete this line!
            parent::Create();

            $this->RegisterPropertyString('WohnungsID','');
            $this->RegisterPropertyInteger('MieterID',0);
            $this->RegisterPropertyInteger('Zählernummer', 0);
            $this->RegisterPropertyInteger('Verbrauch', 0);
            $this->RegisterPropertyInteger('VerbrauchDurchschnitt', 0);
            $this->RegisterPropertyInteger('AbweichungProzentual', 0);

            $this->RegisterPropertyInteger('LevelOfDetail', 0);
            $this->RegisterPropertyBoolean('EnergieKonfig',false);
            $this->RegisterPropertyInteger('EnergieKonfigInstanz', 0);

            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        }

        public function ApplyChanges()
        {
            parent::ApplyChanges();

            //Get profile
            $timeProfile = '';
            $levelOfDetail = $this->ReadPropertyInteger('LevelOfDetail');
            switch ($levelOfDetail) {
                case LOD_DATE:
                    $timeProfile = '~UnixTimestampDate';
                    break;
                case LOD_TIME:
                    $timeProfile = '~UnixTimestampTime';
                    break;
                case LOD_DATETIME:
                    $timeProfile = '~UnixTimestamp';
                    break;
            }

            $this->RegisterVariableInteger('StartDatum', 'Start-Datum', $timeProfile, 1);
            $this->EnableAction('StartDatum');

            $this->RegisterVariableInteger('EndDatum', 'End-Datum', $timeProfile, 2);
            $this->EnableAction('EndDatum');

            //Only call this in READY state. On startup the ArchiveControl instance might not be available yet
            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->setupInstance();
            }
        }

        public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
        {
            if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
                $this->setupInstance();
            }
        }

        public function RequestAction($Ident, $Value)
        {
            switch ($Ident) {
                case 'StartDatum':
                case 'EndDatum':
                    //Neuen Wert in die Statusvariable schreiben
                    if (date('s', $Value) != 0) {
                        $this->SetValue($Ident, strtotime(date('d-m-Y H:i:00', $Value)));
                        break;
                    } else {
                        $this->SetValue($Ident, $Value);
                    }
                    //Berechnen
                    $this->SetValue('Zaehlernummer', GetValue($this->ReadPropertyInteger('Zählernummer')));
                    $this->Calculate($this->ReadPropertyInteger('Verbrauch'), 'Verbrauch');
                    $this->Calculate($this->ReadPropertyInteger('VerbrauchDurchschnitt'), 'VerbrauchDurchschnitt');

                    $AbweichungDurchschnitt = ($this->GetValue('Verbrauch') - $this->GetValue('VerbrauchDurchschnitt'));
                    $this->SetValue('AbweichungDurchschnitt', $AbweichungDurchschnitt);

                    $AbweichungProzentual = ($this->GetValue('Verbrauch') - $this->GetValue('VerbrauchDurchschnitt')) / (($this->GetValue('VerbrauchDurchschnitt') + $this->GetValue('Verbrauch')) / 2) * 100;
                    $this->SetValue('AbweichungProzentual', $AbweichungProzentual);
                    break;
                default:
                    throw new Exception('Invalid Ident');
            }
        }

        public function Calculate($variableID, $Ident)
        {
            $this->SetInstanceStatus();
            if ($this->GetStatus() != 102) {
                $this->SetValue('Zählernummer', 0);
                $this->SetValue('Verbrauch', 0);
                $this->SetValue('VerbrauchDurchschnitt', 0);
                $this->SetValue('AbweichungDurchschnitt', 0);
                $this->SetValue('AbweichungProzentual', 0);
            }

            $acID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
            $levelOfDetail = $this->ReadPropertyInteger('LevelOfDetail');
            $startDate = GetValue($this->GetIDForIdent('StartDatum'));
            $endDate = GetValue($this->GetIDForIdent('EndDatum'));
            //Reduce enddate if lod is not date
            if ($levelOfDetail != LOD_DATE) {
                $endDate--;
            }
            //Set startDate/endDate for LOD_TIME to same day
            if ($levelOfDetail == LOD_TIME) {
                $startDate = strtotime(date('H:i:s', $startDate), $this->getTime());
                $endDate = strtotime(date('H:i:s', $endDate), $this->getTime());
            } elseif ($levelOfDetail == LOD_DATE) {
                $startDate = strtotime(date('d.m.Y 00:00:00', $startDate), $startDate);
                $endDate = strtotime(date('d.m.Y 23:59:59', $endDate), $endDate);
            }
            $this->SendDebug('Start Datum Calculate',$startDate,0);

            if (($startDate == $endDate) || ($startDate > $endDate)) {
                $this->SetValue($Ident, 0);
                return;
            }

            $values = [];
            $sum = 0;
            if ($levelOfDetail == LOD_DATE) {
                $values = array_merge($values, $this->GetAggregatedValues($variableID, 1 /* Day */, $startDate, $endDate, 0));
            //Check if startDate/endDate are in the same hour
            } elseif (date('d.m.Y H', $startDate) == date('d.m.Y H', $endDate)) {
                $values = array_merge($values, $this->GetAggregatedValues($variableID, 6 /* Minutes */, $startDate, $endDate, 0));
            } else {
                //FirstMinutes
                $this->SendDebug('FirstMinutsStart', date('H:i:s', $startDate), 0);
                //StartDate at H:59:59
                $firstMinutesEnd = strtotime(date('H', $startDate) . ':59:59', $startDate);
                $this->SendDebug('FirstMinutsEnd', date('H:i:s', $firstMinutesEnd), 0);
                $values = array_merge($values, $this->GetAggregatedValues($variableID, 6 /* Minutes */, $startDate, $firstMinutesEnd, 0));

                //LastMinutes
                //Full hour of endDate
                $lastMinutesStart = strtotime(date('H', $endDate) . ':00:00', $endDate);
                $this->SendDebug('LastMinutsStart', date('H:i:s', $lastMinutesStart), 0);
                $this->SendDebug('LastMinutsEnd', date('H:i:s', $endDate), 0);
                $values = array_merge($values, $this->GetAggregatedValues( $variableID, 6 /* Minutes */, $lastMinutesStart, $endDate, 0));

                //FirstHour start/end
                $hoursStart = $firstMinutesEnd + 1;
                $hoursEnd = $lastMinutesStart - 1;
                if (date('d.m.Y', $startDate) == date('d.m.Y', $endDate)) {
                    //Hours
                    $this->SendDebug('StartHours', date('H:i:s', $hoursStart), 0);
                    $this->SendDebug('EndHours', date('H:i:s', $hoursEnd), 0);
                    $values = array_merge($values, $this->GetAggregatedValues($variableID, 0 /* Hour */, $hoursStart, $hoursEnd, 0));
                } else {
                    //FirstHours
                    $this->SendDebug('FirstHoursStart', date('d.m.Y H:i:s', $hoursStart), 0);
                    //23:59:59 on startDate
                    $firstHoursEnd = strtotime('23:59:59', $startDate);
                    $this->SendDebug('FirstHoursEnd', date('d.m.Y H:i:s', $firstHoursEnd), 0);
                    $values = array_merge($values, $this->GetAggregatedValues($variableID, 0 /* Hour */, $hoursStart, $firstHoursEnd, 0));

                    //LastHours
                    //00:00:00 on endDate
                    $lastHoursStart = strtotime('00:00:00', $endDate);
                    $this->SendDebug('LastHoursStart', date('d.m.Y H:i:s', $lastHoursStart), 0);
                    $this->SendDebug('LastHoursEnd', date('d.m.Y H:i:s', $hoursEnd), 0);
                    $values = array_merge($values, $this->GetAggregatedValues($variableID, 0 /* Hour */, $lastHoursStart, $hoursEnd, 0));

                    //Days
                    $daysStart = $firstHoursEnd + 1;
                    $this->SendDebug('StartDays', date('d.m.Y H:i:s', $daysStart), 0);
                    $daysEnd = $lastHoursStart - 1;
                    $this->SendDebug('EndDays', date('d.m.Y H:i:s', $daysEnd), 0);
                    $values = array_merge($values, $this->GetAggregatedValues($variableID, 1 /* Day */, $daysStart, $daysEnd, 0));
                }
            }

            if ($values === false) {
                $this->SendDebug('Error', 'NoData', 0);
                return;
            }

            foreach ($values as $value) {
                $sum += $value['Avg'];
            }

            $this->SetValue($Ident, $sum);
        }

        private function getProfileFromVariable($varID)
        {
            $v = IPS_GetVariable($varID);
            $sourceProfile = '';
            $sourceProfile = $v['VariableCustomProfile'];
            if ($sourceProfile == '') {
                $sourceProfile = $v['VariableProfile'];
            }
            return $sourceProfile;
        }

        private function registerVariableWithSourceProfile($sourceVariable, $Ident, $Name, $Postion)
        {
            $v = IPS_GetVariable($sourceVariable);
            $sourceProfile = $this->getProfileFromVariable($sourceVariable);

            switch ($v['VariableType']) {
                case 1: /* Integer */
                    $this->RegisterVariableInteger($Ident, $Name, $sourceProfile, $Postion);
                    break;

                case 2: /* Float */
                    $this->RegisterVariableFloat($Ident, $Name, $sourceProfile, $Postion);
                    break;

                default:
                    return;
            }

            //Add references
            foreach ($this->GetReferenceList() as $referenceID) {
                $this->UnregisterReference($referenceID);
            }
            if (IPS_VariableExists($sourceVariable)) {
                $this->RegisterReference($sourceVariable);
            }
        }

        private function setupInstance()
        {
            $this->SetInstanceStatus();

            if ($this->GetStatus() != 102) {
                return;
            }

            $this->registerVariableWithSourceProfile($this->ReadPropertyInteger('Zählernummer'), 'Zaehlernummer', 'Zählernummer', 3);
            $this->registerVariableWithSourceProfile($this->ReadPropertyInteger('Verbrauch'), 'Verbrauch', 'Verbrauch', 4);
            $this->registerVariableWithSourceProfile($this->ReadPropertyInteger('VerbrauchDurchschnitt'), 'VerbrauchDurchschnitt', 'Verbrauch Durchschnitt', 8);
            $this->RegisterVariableFloat('AbweichungDurchschnitt', 'Abweichung Durchschnitt', $this->getProfileFromVariable($this->ReadPropertyInteger('Verbrauch')), 9);
            $this->RegisterVariableInteger('AbweichungProzentual', 'Abweichung Prozentual', '~Intensity.100', 10);
        }

        private function SetInstanceStatus()
        {
            //Property, Logging, LoggingType, Status nicht selektiert, Status falscher Logging Type
            $variables = [
                [$this->ReadPropertyInteger('Zählernummer'), false, 0, null, null],
                [$this->ReadPropertyInteger('Verbrauch'), true, 1, 201, 202],
                [$this->ReadPropertyInteger('VerbrauchDurchschnitt'), true, 1, 207, 208],
                [$this->ReadPropertyInteger('AbweichungProzentual'), true, 0, 209, 210]
            ];

            $archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
            foreach ($variables as $key => $variable) {
                $varID = $variable[0];
                $logging = $variable[1];
                $loggingTyp = $variable[2];
                $StatusLogging = $variable[3];
                $StatusLoggingTyp = $variable[4];
                //No variable selected
                if ($varID == 0) {
                    $this->SetStatus(104);
                    return;
                }
                //Selected variable doesn't exist
                if (!IPS_VariableExists($varID)) {
                    $this->SetStatus(200);
                    return;
                }
                //Check logging
                if ($logging) {
                    if (AC_GetLoggingStatus($archiveID, $varID) == false) {
                        $this->SetStatus($StatusLogging);
                        return;
                    } elseif (AC_GetAggregationType($archiveID, $varID) != $loggingTyp) {
                        $this->SetStatus($StatusLoggingTyp);
                        return;
                    }
                }
            }

            $sourceID = $varID;

            //Everything ok
            if ($this->GetStatus() != 102) {
                $this->SetStatus(102);
            }
        }


        //TODO Funktion gegen die originale austauschen
        private function GetAggregatedValues($variableID, $aggregation, $startDate, $endDate, $limit) {
            $this->SendDebug('Start Datum',$startDate,0);
            $this->SendDebug('End Datum',$endDate,0);
            $WohnungsID = $this->ReadPropertyString('WohnungsID');
            $MieterID = $this->ReadPropertyInteger('MieterID');
            $EnergieKonfigInstanz = $this->ReadPropertyInteger('EnergieKonfigInstanz');
            $acID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
            if ($this->ReadPropertyBoolean('EnergieKonfig')) {
                if ($EnergieKonfigInstanz == 0) {
                    $EnergieKonfigInstanz = IPS_GetInstanceListByModuleID('{3BE56E7A-C2AC-91A9-0A0D-397C4345B065}')[0];
            }
            return VER_GetLoggedValues($EnergieKonfigInstanz, $variableID, $aggregation, $WohnungsID, $MieterID, $startDate, $endDate, $limit);
            }
            return AC_GetAggregatedValues($acID, $variableID, $aggregation, $startDate, $endDate, $limit);
        }
    
    }