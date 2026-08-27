<?php

namespace App\Http\Traits;

use DateTime;

trait GetBlockValues
{
    // $rpu, no longer gets used
    public function getBlock($fecha, $hour, $rpu, $dataFromChargeCenter, $diasFestivos)
    {
        // echo 'In getBlock trait';exit;
        $dayInLetter = DateTime::createFromFormat('Y-m-d', $fecha);
        $dayInLetter = $dayInLetter->format('l');
        $dayInt = $this->setDayToInt($dayInLetter);
        $isWeekDay = ($dayInt >= 1 && $dayInt <= 5);
        $isHoliday = in_array($fecha, $diasFestivos);
    
        $finalBlock = "";
        switch ($dataFromChargeCenter['sistema']) {
            case 'BCA':
                $station = $this->checkYearStation($fecha, 'twoSeasonVarient');
                if ($dataFromChargeCenter['grupoTarifario'] == 'GDMTH') {
                    /* BCA Verano GDMTH */ //Tabla 4
                    if ($station == 2 && $isHoliday) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 1 && $hour <= 14)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 15 && $hour <= 18)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 19 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($dayInt == 6 || $dayInt == 7)) {
                        $finalBlock = "I";
                    /* BCA Invierno GDMTH */ //Tabla 5
                    } elseif ($station == 4 && $isHoliday) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 17)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 18 && $hour <= 22)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 19 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7) {
                        $finalBlock = "B";
                    } else {
                        $finalBlock = "-";
                    }
                } elseif ($dataFromChargeCenter['grupoTarifario'] == 'DIST') {
                    /* BCA Verano DIST */ //Tabla 6
                    if ($station == 2 && $isHoliday) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 1 && $hour <= 12)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 13 && $hour <= 14)) {
                        $finalBlock = "S";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 15 && $hour <= 18)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 19 && $hour <= 22)) {
                        $finalBlock = "S";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($dayInt == 6 || $dayInt == 7)) {
                        $finalBlock = "I";
                    /* BCA Invierno DIST */ //Tabla 7
                    } elseif ($station == 4 && $isHoliday) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 17)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 18 && $hour <= 22)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 19 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7) {
                        $finalBlock = "B";
                    } else {
                        $finalBlock = "-";
                    }
                } elseif ($dataFromChargeCenter['grupoTarifario'] == 'DIT') {
                    /* BCA Verano DIT */ //Tabla 8
                    if ($station == 2 && $isHoliday) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 1 && $hour <= 13)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 14 && $hour <= 17)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 18 && $hour <= 23)) {
                        $finalBlock = "S";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($dayInt == 6 || $dayInt == 7)) {
                        $finalBlock = "I";
                    /* BCA invierno DIT */ //Tabla 9
                    } elseif ($station == 4 && $isHoliday) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 17)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 18 && $hour <= 22)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 19 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7) {
                        $finalBlock = "B";
                    } else {
                        $finalBlock = "-";
                    }
                } else {
                    // print_r("Hay un error con el grupo tarifario");
                    return;
                }
                break;
            case 'BCS':
                $station = $this->checkYearStation($fecha, 'twoSeason');
                if ($dataFromChargeCenter['grupoTarifario'] == 'GDMTH') {
                    /* BCS Verano GDMTH */ //Tabla 10
                    if ($station == 2 && $isHoliday) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 1 && $hour <= 12)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 13 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 20 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 7) {
                        $finalBlock = "I";
                    /* BCS Invierno GDMTH */ //Tabla 11
                    } elseif ($station == 4 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 20 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 19 && $hour <= 22)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 19 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 20 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } else {
                        $finalBlock = "-";
                    }
                } elseif ($dataFromChargeCenter['grupoTarifario'] == 'DIST') {
                    /* BCS Verano DIST */ //Tabla 12
                    if ($station == 2 && $isHoliday) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 1 && $hour <= 12)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 13 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 20 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 7) {
                        $finalBlock = "I";
                    /* BCS Invierno DIST */ //Tabla 13
                    } elseif ($station == 4 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 20 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 19 && $hour <= 22)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 19 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 20 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } else {
                        $finalBlock = "-";
                    }
                } elseif ($dataFromChargeCenter['grupoTarifario'] == 'DIT') {
                    /* BCS Verano DIT */ //Tabla 14
                    if ($station == 2 && $isHoliday) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 1 && $hour <= 13)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 14 && $hour <= 23)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 1 && $hour <= 20)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 21 && $hour <= 23)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 7) {
                        $finalBlock = "I";
                    /* BCS Invierno DIT */ //Tabla 15
                    } elseif ($station == 4 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 20 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 19 && $hour <= 22)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 19 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 20 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "B";
                    } else {
                        $finalBlock = "-";
                    }
                } else {
                    // print_r("Hay un error con el grupo tarifario");
                    return;
                }
                break;
            case 'SIN':
                if ($dataFromChargeCenter['grupoTarifario'] == 'GDMTH') {
                    $station = $this->checkYearStation($fecha, 'twoSeason');
                    /* SIN Verano GDMTH */ //Tabla 16
                    if ($station == 2 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $isHoliday && ($hour >= 20 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 1 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 7 && $hour <= 20)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 21 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 1 && $hour <= 7)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 8 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $dayInt == 7 && ($hour >= 20 && $hour <= 25)) {
                        $finalBlock = "I";
                    /* SIN Invierno GDMTH */ //Tabla 17
                    } elseif ($station == 4 && $isHoliday && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 19 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 7 && $hour <= 18)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 19 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 8)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 9 && $hour <= 19)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 20 && $hour <= 21)) {
                        $finalBlock = "P";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 19 && $hour <= 25)) {
                        $finalBlock = "I";
                    } else {
                        $finalBlock = "-";
                    }
                } elseif ($dataFromChargeCenter['grupoTarifario'] == 'DIST') {
                    $station = $this->checkYearStation($fecha, 'fourSeason');
                    /* SIN Primavera DIST */ //Tabla 18
                    if ($station == 1 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && $isHoliday && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && $isHoliday && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && ($isWeekDay) && ($hour >= 1 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && ($isWeekDay) && ($hour >= 7 && $hour <= 19)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && ($isWeekDay) && ($hour >= 20 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 1 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && $dayInt == 6 && ($hour >= 1 && $hour <= 7)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && $dayInt == 6 && ($hour >= 8 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && $dayInt == 7 && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && $dayInt == 7 && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "B";
                    /* SIN Verano DIST */ //Tabla 19
                    } elseif ($station == 2 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $isHoliday && ($hour >= 20 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && $hour == 1) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 2 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 7 && $hour <= 20)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 21 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && $hour == 1) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 2 && $hour <= 7)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 8 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $dayInt == 7 && ($hour >= 20 && $hour <= 25)) {
                        $finalBlock = "I";
                    /* SIN Otoño DIST */ //Tabla 20
                    } elseif ($station == 3 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && $isHoliday && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && $isHoliday && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && ($isWeekDay) && ($hour >= 1 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && ($isWeekDay) && ($hour >= 7 && $hour <= 19)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && ($isWeekDay) && ($hour >= 20 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 3 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && $dayInt == 6 && ($hour >= 1 && $hour <= 7)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && $dayInt == 6 && ($hour >= 8 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && $dayInt == 7 && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && $dayInt == 7 && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "B";
                    /* SIN Invierno DIST */ //Tabla 21
                    } elseif ($station == 4 && $isHoliday && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 19 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 7 && $hour <= 18)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 19 && $hour <= 22)) {
                        $finalBlock = "P";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 23 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 8)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 9 && $hour <= 19)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 20 && $hour <= 21)) {
                        $finalBlock = "P";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 22 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 19 && $hour <= 25)) {
                        $finalBlock = "I";
                    } else {
                        $finalBlock = "-";
                    }
                } elseif ($dataFromChargeCenter['grupoTarifario'] == 'DIT') {
                    $station = $this->checkYearStation($fecha, 'fourSeason');
                    /* SIN Primavera DIT */ //Tabla 22
                    if ($station == 1 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && $isHoliday && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && $isHoliday && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && ($isWeekDay) && ($hour >= 1 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && ($isWeekDay) && ($hour >= 7 && $hour <= 20)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && ($isWeekDay) && ($hour >= 21 && $hour <= 23)) {
                        $finalBlock = "P";
                    } elseif ($station == 1 && ($isWeekDay) && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && $dayInt == 6 && ($hour >= 1 && $hour <= 7)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && $dayInt == 6 && ($hour >= 8 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 1 && $dayInt == 7 && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "I";
                    } elseif ($station == 1 && $dayInt == 7 && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "B";
                    /* SIN Verano DIT */ //Tabla 23
                    } elseif ($station == 2 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $isHoliday && ($hour >= 20 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && $hour == 1) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 2 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 7 && $hour <= 21)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour >= 22 && $hour <= 23)) {
                        $finalBlock = "P";
                    } elseif ($station == 2 && ($isWeekDay) && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && $hour == 1) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 2 && $hour <= 7)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $dayInt == 6 && ($hour >= 8 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 2 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 2 && $dayInt == 7 && ($hour >= 20 && $hour <= 25)) {
                        $finalBlock = "I";
                    /* SIN otoño DIT */ //Tabla 24
                    } elseif ($station == 3 && $isHoliday && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && $isHoliday && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && $isHoliday && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && ($isWeekDay) && ($hour >= 1 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && ($isWeekDay) && ($hour >= 7 && $hour <= 20)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && ($isWeekDay) && ($hour >= 21 && $hour <= 23)) {
                        $finalBlock = "P";
                    } elseif ($station == 3 && ($isWeekDay) && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && $dayInt == 6 && ($hour >= 1 && $hour <= 7)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && $dayInt == 6 && ($hour >= 8 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && $dayInt == 7 && ($hour >= 1 && $hour <= 19)) {
                        $finalBlock = "B";
                    } elseif ($station == 3 && $dayInt == 7 && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "I";
                    } elseif ($station == 3 && $dayInt == 7 && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "B";
                    /* SIN invierno DIT */ //Tabla 25
                    } elseif ($station == 4 && $isHoliday && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $isHoliday && ($hour >= 19 && $hour <= 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 1 && $hour <= 6)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 7 && $hour <= 19)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour >= 20 && $hour <= 23)) {
                        $finalBlock = "P";
                    } elseif ($station == 4 && ($isWeekDay) && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 1 && $hour <= 8)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 9 && $hour <= 20)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour >= 21 && $hour <= 23)) {
                        $finalBlock = "P";
                    } elseif ($station == 4 && $dayInt == 6 && ($hour == 24 || $hour == 25)) {
                        $finalBlock = "I";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 1 && $hour <= 18)) {
                        $finalBlock = "B";
                    } elseif ($station == 4 && $dayInt == 7 && ($hour >= 19 && $hour <= 25)) {
                        $finalBlock = "I";
                    } else {
                        $finalBlock = "-";
                    }
                } else {
                    // print_r("Hay un error con el grupo tarifario");
                    return;
                }
                break;
        }
        return $finalBlock;
    }

    public function checkYearStation($date, $seasonType)
    {
        /*
        Verano      1 de mayo               último domingo octubre
        Invierno    último domingo octubre  30 de abril

        Verano      primer domingo abril    sábado anterior al último domingo de octubre
        Invierno    último domingo octubre  sábado anterior al primer domingo de abril

        Verano      primer domingo abril    sábado anterior al último domingo de octubre
        Invierno    último domingo octubre  sábado anterior al primer domingo de abril

        Primavera   1 de febrero            sábado anterior al primer domingo de abril
        Verano      primer domingo abril    31 de julio
        Otoño       1 de agosto             sábado anterior al último domingo de octubre
        Invierno    último domingo octubre  31 de enero
        */
        /* Limpiamos el año de la fecha para comparar sólo mes y día */
        $newDate = date('Y-m-d', strtotime($date));
        $year = substr($date, 0, 4);
        $nextYear = $year + 1;
        $station = "";
        switch ($seasonType) {
            case 'twoSeasonVarient':
                $beginSummer = date('Y-m-d', strtotime($year . "-05-01"));
                $endSummer = date('Y-m-d', strtotime("-1 day", strtotime("last Sunday of October " . $year)));
                $beginWinter = date('Y-m-d', strtotime("last Sunday of October " . $year));
                $endWinter = date('Y-m-d', strtotime($nextYear . "-04-30"));
    
                if ($this->isDateBewteenRange($newDate, $beginSummer, $endSummer)) {
                    $station = 2;
                } elseif ($this->isDateBewteenRange($newDate, $beginWinter, $endWinter)) {
                    $station = 4;
                } else {
                    $station = 4;
                }
                break;
            case 'twoSeason':
                $beginSummer = date('Y-m-d', strtotime("first Sunday of April " . $year));
                $endSummer = date('Y-m-d', strtotime("-1 day", strtotime("last Sunday of October " . $year)));
                $beginWinter = date('Y-m-d', strtotime("last Sunday of October " . $year));
                $endWinter = date('Y-m-d', strtotime("-1 day", strtotime("first Sunday of April " . $nextYear)));
    
                if ($this->isDateBewteenRange($newDate, $beginSummer, $endSummer)) {
                    $station = 2;
                } elseif ($this->isDateBewteenRange($newDate, $beginWinter, $endWinter)) {
                    $station = 4;
                } else {
                    $station = 4;
                }
                break;
            case 'fourSeason':
                $beginSpring = date('Y-m-d', strtotime($year . "-02-01"));
                $endSpring = date('Y-m-d', strtotime("-1 day", strtotime("first Sunday of April " . $year)));
    
                $beginSummer = date('Y-m-d', strtotime("first Sunday of April " . $year));
                $endSummer = date('Y-m-d', strtotime($year . "-07-31"));
    
                $beginAutumn = date('Y-m-d', strtotime($year . "-08-01"));
                $endAutumn = date('Y-m-d', strtotime("-1 day", strtotime("last Sunday of October " . $year)));
    
                $beginWinter = date('Y-m-d', strtotime("last Sunday of October " . $year));
                $endWinter = date('Y-m-d', strtotime($nextYear . "-01-31"));
    
                if ($this->isDateBewteenRange($newDate, $beginSpring, $endSpring)) {
                    $station = 1;
                } elseif ($this->isDateBewteenRange($newDate, $beginSummer, $endSummer)) {
                    $station = 2;
                } elseif ($this->isDateBewteenRange($newDate, $beginAutumn, $endAutumn)) {
                    $station = 3;
                } elseif ($this->isDateBewteenRange($newDate, $beginWinter, $endWinter)) {
                    $station = 4;
                } else {
                    $station = 4;
                }
                break;
        }
        return $station;
    }

    public function setDayToInt($day)
    {
        $dayInt = "";
        switch ($day) {
            case 'Monday':
                $dayInt = 1;
                break;
            case 'Tuesday':
                $dayInt = 2;
                break;
            case 'Wednesday':
                $dayInt = 3;
                break;
            case 'Thursday':
                $dayInt = 4;
                break;
            case 'Friday':
                $dayInt = 5;
                break;
            case 'Saturday':
                $dayInt = 6;
                break;
            case 'Sunday':
                $dayInt = 7;
                break;
        }
        return $dayInt;
    }

    public function isDateBewteenRange($date, $startRangeDate, $endRangeDate)
    {
        return ($date >= $startRangeDate) && ($date <= $endRangeDate);
    }
}
