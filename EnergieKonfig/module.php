<?php

class EnergieKonfig extends IPSModule {
    public function Create() {
        parent::Create();
        $this->RegisterPropertyString('Zaehler', json_encode([]));
        $this->RegisterPropertyString('Wohnungen', json_encode([]));
        $this->RegisterPropertyInteger('PropertyInstanceID',0);
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
    }

    public function GetConfigurationForm() {
        return file_get_contents(__DIR__ . '/form.json');
    }

    public function getZaehler() {
        $Zaehler = json_decode($this->ReadPropertyString('Zaehler'),true);
        $Zaehler = array_combine(array_column($Zaehler, 'Name'), $Zaehler);
        return $Zaehler;
    }

    public function getWohnungen() {
        $Wohnungen = json_decode($this->ReadPropertyString('Wohnungen'),true);
        $Wohnungen = array_combine(array_column($Wohnungen, 'Name'), $Wohnungen);
        return $Wohnungen;        
    }


    public function GetLoggedValues(int $variableID, $aggregationsStufe, $WohnungsID, $startDatum, $endDatum, $limit) {

        if ($this->ReadPropertyInteger('PropertyInstanceID') == 0) {
            $archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
        } else {
            $archiveID = $this->ReadPropertyInteger('PropertyInstanceID');
        }      

        $Wohnungen = json_decode($this->ReadPropertyString('Wohnungen'),true);

        foreach ($Wohnungen as $Wohnung) {
            if ($Wohnung['Name'] == $WohnungsID) {
                $einzugsDatum = json_decode($Wohnung['Einzugsdatum'],true);
                $timestampEinzug = strtotime($einzugsDatum['day'].'.'.$einzugsDatum['month'].'.'.$einzugsDatum['year']);
            }
        }
      
        if ($timestampEinzug === false) {
            echo "Ungültiges Einzugsdatum";
            return;
        }
        if ($timestampEinzug > $startDatum) {

            try {
                throw new Exception('Das Startdatum muss vor dem Einzugsdatum '. date('d.m.Y', $timestampEinzug). ' liegen');
            } catch (Exception $e) {
                echo "Fehler: " . $e->getMessage(); // Stacktrace wird nicht ausgegeben
                exit;
            }
        }
        if ($timestampEinzug > $endDatum) {
            try {
                throw new Exception('Das Endatum muss vor dem Einzugsdatum '. date('d.m.Y', $timestampEinzug). ' liegen');
            } catch (Exception $e) {
                echo "Fehler: " . $e->getMessage(); // Stacktrace wird nicht ausgegeben
                exit;
            }
        }

        $data = AC_GetAggregatedValues($archiveID, $variableID, $aggregationsStufe, $startDatum, $endDatum, $limit);
        return $data;
    }
}