<?php
/*
*
* CLASSE DI UNA FATTURA PER L'IMPORTAZIONE
* IN LYNFA POLYEDRO TRAMITE TRAF2000
*
*
* PER IL MOMENTO LA CLASSE FUNZIONA SOLAMENTE
* PER LE FATTURE DI VENDITA
*
*/

//includo la libreria eInvoice
require_once('php-e-invoice-it/vendor/autoload.php');
require_once('../XMLtoTRAF2000/vendor/autoload.php');
use Taocomp\Einvoicing\FatturaElettronica;
use FilippoToso\P7MExtractor\P7M;

class Fattura {
    //collegamento con fattura elettronica
    public $fe;
    
    //****************************************
    //***---DEFINIZIONE ELEMENTI FATTURA---***
    //****************************************

    //**-dati cliente-**
    public $nome;
    public $cognome;
    public $ragioneSociale;
    public $isPersonaFisica = true;
    public $via;
    public $CAP;
    public $citta;
    public $provincia;
    public $codiceFiscale;
    public $partitaIva;
    public $paese;

    //**-dati fattura-**
    public $causale;
    public $dataRegistrazione;
    public $dataDocumento;
    public $numeroDocFornitore;
    public $numeroDoc;
    public $sezionaleIva;

    //**-dati iva-**
    //la prossima riga vale sia per dati iva che per conti di ricavo/costo
    public $shouldImportRiga = array(false, false, false, false, false, false, false, false);
    //al massimo 8 righe per fattura
    public $imponibile = array();
    public $aliquota = array();
    public $iva11 = array();
    public $imposta = array();

    //**-totale fattura-**
    public $totaleFatt;

    //**-conti di ricavo/costo-**
    //al massimo 8 righe per fattura
    public $conto = array();
    public $importoConto = array();
    
    //**-dati pagamento fattura-**
    public $fatturaPagata;
    public $contoPagamento;
    public $importoPagamento;

    //********************
    //***---FUNZIONI---***
    //********************

    public function __construct($type, $path) {
        if ($type=="xml") {
            $this->importFromXml($path);
        }
        if ($type=="p7m") {
            P7M::convert($path, 'tempGeneratedFile.xml');
            $this->importFromXml("tempGeneratedFile.xml");
            unlink("tempGeneratedFile.xml");
        }
    }

    function importFromXml($xml) {
        //importo la fattura
        try {
            error_log("*********TEMPO 1: ".round(microtime(true) * 1000));
            
            //creo l'oggetto eInvoice
            $this->fe = new FatturaElettronica($xml);
            
            error_log("*********TEMPO 2: ".round(microtime(true) * 1000));
            
            //riempio l'oggetto fattura
            $this->nome = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/DatiAnagrafici/Anagrafica/Nome");
            $this->cognome = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/DatiAnagrafici/Anagrafica/Cognome");
            $this->ragioneSociale = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/DatiAnagrafici/Anagrafica/Denominazione");
            $this->via = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/Sede/Indirizzo");
            $this->CAP = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/Sede/CAP");
            $this->citta = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/Sede/Comune");
            $this->provincia = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/Sede/Provincia");
            $this->codiceFiscale = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/DatiAnagrafici/CodiceFiscale");
            $this->partitaIva = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/DatiAnagrafici/IdFiscaleIVA/IdCodice");
            $this->paese = $this->fe->getValue("FatturaElettronicaHeader/CessionarioCommittente/Sede/Nazione");

            if ($this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/TipoDocumento") == "TD01" ||
                $this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/TipoDocumento") == "TD02" ||
                $this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/TipoDocumento") == "TD03" ||
                $this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/TipoDocumento") == "TD05" ||
                $this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/TipoDocumento") == "TD06") {
                $this->causale = "1";
            } else {
                $this->causale = "2";
            }
            $this->dataRegistrazione = strtotime($this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/Data"));
            $this->dataDocumento = strtotime($this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/Data"));
            //TODO controllare cosa mettere in numerodocfornitore
            $this->numeroDoc = $this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/Numero");
            $this->totaleFatt = $this->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/ImportoTotaleDocumento");
            
            
            error_log("*********TEMPO 3: ".round(microtime(true) * 1000));
            
        } catch (Exception $e) {
            error_log("Errore: ".$e->GetMessage()."\n");
        }
        return true;
    }

    public function exportTRAF2000() {
        //variabile che verrà riempita
        $str = "0000130";

        //*-dati cliente fornitore-*
        $str .= "00000";
        if ($this->isPersonaFisica) {
            //compilo cognome e nome
            $str .= left($this->cognome." ".$this->nome, 32, " ");
        } else {
            //compilo ragione sociale
            $str .= left($this->ragioneSociale, 32, " ");
        }
        $str .= left($this->via, 30, " ");
        $str .= left($this->CAP, 5, " ");
        $str .= left($this->citta, 25, " ");
        $str .= left($this->provincia, 2, " ");
        $str .= left($this->codiceFiscale, 16, " ");
        $str .= left($this->partitaIva, 11, " ");
        if ($this->isPersonaFisica) { //TODO controllare per valore P
            $str .= "S".right(strlen($this->cognome)+1, 2, "0");
        } else {
            $str .= "N00";
        }
        $str .= str_repeat(" ", 131);

        //*-dati fattura-*
        $str .= right($this->causale, 3, "0");
        $str .= left("Fattura vendita", 15, " "); //TODO aggiornare per altre causali
        $str .= str_repeat(" ", 86);
        $str .= left(date("dmY" ,$this->dataRegistrazione), 8, " ");
        $str .= left(date("dmY" ,$this->dataDocumento), 8, " ");
        $str .= str_repeat(" ", 8);
        $str .= right($this->numeroDoc, 5, "0");
        $str .= left($this->sezionaleIva, 2, "0");
        $str .= str_repeat(" ", 72);

        //*-dati iva-*
        for ($i=0; $i<8; $i++) {
            if ($this->shouldImportRiga[$i]) {
                //TODO controllare come gestire i segni
                $str .= right((int)($this->imponibile[$i]*100), 11, "0")."+";
                $str .= right($this->aliquota[$i], 3, "0");
                $str .= "000";
                $str .= right($this->iva11[$i], 2, "0");
                $str .= right((int)($this->imposta[$i]*100), 10, "0")."+";
            } else {
                $str .= str_repeat(" ", 31);
            }
        }
        $str .= right((int)($this->totaleFatt*100), 11, "0")."+";

        //*-conti di ricavo/costo-*
        for ($i=0; $i<8; $i++) {
            if ($this->shouldImportRiga[$i]) {
                $str .= right($this->conto[$i], 7, "0");
                //TODO controllare come gestire i segni
                $str .= right((int)($this->importoConto[$i]*100), 11, "0")."+";
            } else {
                $str .= str_repeat(" ", 19);
            }
        }

        //*-dati evenutale pagamento-*
        if ($this->fatturaPagata) {
            $str .= "027";
            $str .= left("Pag. Fatt. ".(int)($this->numeroDoc), 15, " ");
            $str .= str_repeat(" ", 68);
            /* dare1 */
            $str .= "9999999"; //cliente in base a CF
            $str .= "A";
            //TODO controllare come gestire i segni
            $str .= right((int)($this->importoPagamento*100), 11, "0")."+";
            $str .= str_repeat(" ", 44);
            
            /* avere1 */
            $str .= right($this->contoPagamento, 7, "0");
            $str .= "D";
            //TODO controllare come gestire i segni
            $str .= right((int)($this->importoPagamento*100), 11, "0")."+";
            $str .= str_repeat(" ", 44);
        }
        
        $str .= "\n"; //delimitatore fine fattura
        return $str;
    }
}

function left($str, $length, $char) {
    return substr($str.str_repeat($char, $length), 0, $length);
}

function right($str, $length, $char) {
    return substr(str_repeat($char, $length).$str, -$length);
}
?>