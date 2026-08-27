<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentroCarga extends Model
{

    protected $connection = 'mysql_dev_2';
    protected $table = 'centrosCarga';
    protected $guarded = [];

    protected $fillable = [
        'userId',
        'teamId',
        'anexoElemento',
        'cuentaDeOrden',
        'anexoElementoDelECD',
        'cuentaDeOrdenDelECD',
        'rpu',
        'addToInvoiceRPU',
        'rmu',
        'grupoTarifario',
        'nivelTension',
        'nivelTensionGroup',
        'nodoP',
        'zonaCarga',
        'sistema',
        'divisionDistribucion',
        'factorCarga',
        'created_at',
        'updated_at',
        'contractNumberId',
        'clients',
        'isInvoiceForOneClient',
        'conceptoDeFacturacion',
        'textoAdicional',
        'units',
        'aliasDeCliente',
        'latitude',
        'longitude',
        'fechaInicioDeOperacion',
        'agrupador',
        'usuarioCalificado',
        'useMedicionesMediMEM',
        'tokenMediMEM',
        'useMedicionesWS',
        'useMedicionesSuministrador',
        'medicionesSuministradorUser',
        'medicionesSuministradorPassword',
        'ceAsociada',
        'zonaInyeccionGas',
        'zonaExtraccionGas',
        'address_cc',
        'addToInvoiceACC',
        'useMedicionesMeteocontrol',
        'meteocontrolKey'
    ];
}
