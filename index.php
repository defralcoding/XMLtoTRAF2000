<?php
//includo la libreria per la fattura
require_once('Fattura.php');

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $filesCount = count(array_filter($_FILES["upload"]["tmp_name"]));
    if ($filesCount > 0) {
        //inizializzo il file TRAF2000
        $fileTRAF2000 = fopen("TRAF2000", "w") or die("Unable to open file!");
        fwrite($fileTRAF2000, "");
        fclose($fileTRAF2000);

        $filesFatture = array(); //qui verranno salvati tutti i nomi dei file e le estensioni da aprire successivamente
        $countFatture = 0;

        //riapro il file TRAF2000 per l'esportazione
        $fileTRAF2000 = fopen("TRAF2000", "a") or die("Unable to open file!");

        //analizzo ogni file caricato
        for ($i=0; $i<$filesCount; $i++) {

            //ricavo estensione file
            $ext = pathinfo($_FILES["upload"]["name"][$i])["extension"];

            //aggiunta a elenco in base a tipo di file
            switch ($ext) {
                case "xml":
                case "p7m":
                    //controllo che il file non sia un metadato
                    if (strpos($_FILES["upload"]["name"][$i], '_metaDato') === false) {
                        $filesFatture[$countFatture]["path"] = $_FILES["upload"]["tmp_name"][$i];
                        $filesFatture[$countFatture]["ext"] = $ext;
                        $countFatture++;
                    }
                    break;

                case "zip":
                    //estraggo il file zip
                    $fileZip = new ZipArchive();
                    if ($fileZip->open($_FILES["upload"]["tmp_name"][$i])) {
                        $fileZip->extractTo("tmp/unzippedfiles");
                        $fileZip->close();
                        //ottengo l'elenco dei file unzippati
                        $unzippedFiles = scandir("tmp/unzippedfiles");
                        foreach($unzippedFiles as $unzippedFile) {
                            //controllo che il file sia di tipo XML o P7M
                            $extUnzippedFile = pathinfo("tmp/unzippedfiles".$unzippedFile)["extension"];
                            if ($extUnzippedFile=="xml" || $extUnzippedFile=="p7m") {
                                //controllo che il file non sia un metadato
                                if (strpos($unzippedFile, '_metaDato') === false) {
                                    $filesFatture[$countFatture]["path"] = "tmp/unzippedfiles/".$unzippedFile;
                                    $filesFatture[$countFatture]["ext"] = $extUnzippedFile;
                                    $countFatture++;
                                } else {
                                    //elimino il file di metadato
                                    unlink("tmp/unzippedfiles/".$unzippedFile);
                                }
                            } else {
                                //vari casi particolari
                                if ($unzippedFile=="__MACOSX") {
                                    rmdir("tmp/unzippedfiles/__MACOSX");
                                }
                            }
                        }
                    } else {
                        error_log("Impossibile estrarre i file!");
                    }
                    break;
            }
        }

        error_log("*********TEMPO tot1: ".round(microtime(true) * 1000));
        
        for ($i=0; $i<$countFatture; $i++) {
            //creo l'oggetto fattura
            $fattura = new Fattura($filesFatture[$i]["ext"], $filesFatture[$i]["path"]);
            //aggiungo ulteriori informazioni non presenti nel XML
            $fattura->sezionaleIva = "0"; //TODO controllare questa riga
            $fattura->shouldImportRiga[0] = true;
            $fattura->imponibile[0] = $fattura->fe->getValue("FatturaElettronicaBody/DatiBeniServizi/DatiRiepilogo/ImponibileImporto");
            $fattura->aliquota[0] = "22";
            $fattura->imposta[0] = $fattura->fe->getValue("FatturaElettronicaBody/DatiBeniServizi/DatiRiepilogo/Imposta");
            $fattura->conto[0] = "5810005";
            $fattura->importoConto[0] = $fattura->fe->getValue("FatturaElettronicaBody/DatiBeniServizi/DatiRiepilogo/ImponibileImporto");
            $fattura->fatturaPagata = true;
            $fattura->contoPagamento = "2415501";
            $fattura->importoPagamento = $fattura->fe->getValue("FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/ImportoTotaleDocumento");
            //scrivo le fatture nel file TRAF2000
            fwrite($fileTRAF2000, $fattura->exportTRAF2000());
        }
        
        
        error_log("*********TEMPO tot2: ".round(microtime(true) * 1000));
        
        cleanFolder("tmp/");
        cleanFolder("tmp/unzippedfiles/");

        //chiudo il file TRAF2000
        fclose($fileTRAF2000);
        //scarico il file TRAF2000
        $file_url = "TRAF2000";
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary"); 
        header("Content-disposition: attachment; filename=\"" . basename($file_url) . "\""); 
        readfile($file_url);
        exit();
    }
}

function cleanFolder($dirpath) {
    $handle = opendir($dirpath);
    while (($file = readdir($handle)) !== false) {
        unlink($dirpath . $file);
    }
    closedir($handle);
}

?>

<!DOCTYPE html>
<html lang="it">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Carica XML fattura elettronica</title>

        <!-- Script
============================================ -->

        <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js" integrity="sha384-smHYKdLADwkXOn1EmN1qk/HfnUcbVRZyYmZ4qpPea6sjB/pTJ0euyQp0Mk8ck+5T" crossorigin="anonymous"></script>

        <!-- Fine Script -->

        <!-- Css
============================================ -->

        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" integrity="sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB" crossorigin="anonymous">
        <style>
            .container {
                margin-top: 5vh;
            }
            h2 {
                text-align: center;
                margin-bottom: 25px;
            }
        </style>

        <!-- Fine Css -->

    </head>

    <body>
        <div class="container">
            <?php
            if (isset($error)) {
                print('
                <div class="alert alert-danger" role="alert">
                    '.$error.'
                </div>
                ');
            }
            ?>
            <div class="jumbotron">
                <h2>Carica qui le fatture elettroniche da esportare per Polyedro</h2>
                <form name="formCaricamento" method="post" enctype="multipart/form-data">
                    <input type="file" name="upload[]" multiple="multiple" class="form-control">
                    <br>
                    <input type="submit" name="submit" class="btn btn-primary">
                </form>
            </div>
        </div>
    </body>

</html>
