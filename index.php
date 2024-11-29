<?php

set_time_limit(0);

require 'vendor/autoload.php';

//NECESSARIO UMA CONTA NO GOOGLE CLOUD PARA FUNCIONAR E O PROGRAMA ffmpeg instalado na máquina


use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Storage\StorageClient;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    
<form method="post" enctype="multipart/form-data" action="">
    <p>
        <label>Escolher o audio</label><br/>
        <input type="file" name="userfile" />
        <input type="submit" name="btn" value="enviar"/>
    </p>
</form>



<?php

if(isset($_POST['btn'])){
   
    $arquivo = $_FILES['userfile']['name']; 
    move_uploaded_file($_FILES['userfile']['tmp_name'],__DIR__.'/audios/'.$_FILES['userfile']['name']);

    $file =  __DIR__."/audios/". $arquivo;
    $outputPath = $uploads_dir."/mono_$arquivo";
    converter_audio_para_mono($file, $outputPath);                    
    unlink($file);
    rename($outputPath, $file );
    uploadAudioFileToGCS($file, 'teste-full-001');
    $hertz = obterTaxaAmostragem($file);
    $audioUri = "gs://teste-full-001/audio/$arquivo";
    transcrever_audio_com_longrunning($audioUri,$hertz,$conn,$arquivo);


}




function transcrever_audio_com_longrunning($audioUri,$hertz,$conn,$call) {
    $credentialsPath = __DIR__.'/senha/.............json';
    $status="";
    $dt ="";
    $hora="";
    putenv("GOOGLE_APPLICATION_CREDENTIALS=$credentialsPath");

    $speechClient = new SpeechClient();

    $config = new RecognitionConfig([
        'encoding' => RecognitionConfig\AudioEncoding::LINEAR16,
        'sample_rate_hertz' => $hertz, 
        'language_code' => 'pt-BR', 
    ]);

    $audio = new RecognitionAudio([
        'uri' => $audioUri
    ]);

    try {
        $operationResponse = $speechClient->longRunningRecognize($config, $audio);
        $operationResponse->pollUntilComplete();

        if ($operationResponse->operationSucceeded()) {
            $response = $operationResponse->getResult();

            foreach ($response->getResults() as $result) {
                $alternatives = $result->getAlternatives();
           
                 foreach ($alternatives as $alternative) {
                    echo $alternative->getTranscript();

                 }

                
            }
        } else {
            echo 'Erro ao transcrever o áudio: ' . $operationResponse->getError()->getMessage() . PHP_EOL;
        }
    } catch (Exception $e) {
        echo 'Erro: ' . $e->getMessage() . PHP_EOL;
    } finally {
        $speechClient->close();
    }
}



    function uploadAudioFileToGCS($localFilePath, $bucketName) {
        // Caminho para o arquivo de credenciais da conta de serviço do Google Cloud
        $credentialsPath = __DIR__.'\senha\.............json';
        putenv("GOOGLE_APPLICATION_CREDENTIALS=$credentialsPath");
    
        // Cria o cliente do Google Cloud Storage
        $storage = new StorageClient();
        $bucket = $storage->bucket($bucketName);
        $objectName = 'audio/' . basename($localFilePath);
        $file = fopen($localFilePath, 'r'); // Abre o arquivo local para leitura
        $object = $bucket->upload($file, [
            'name' => $objectName // O nome do arquivo no bucket
        ]);
    
    }
        


function obterTaxaAmostragem($caminhoArquivo) {
    // Abre o arquivo WAV
    $arquivo = fopen($caminhoArquivo, 'rb');
    if (!$arquivo) {
        return false; // Erro ao abrir o arquivo
    }
    $cabecalho = fread($arquivo, 44);
    fclose($arquivo);

    if (strlen($cabecalho) < 44) {
        return false;
    }

    // A taxa de amostragem está localizada nos bytes 24 a 27 do cabeçalho (índices 24-27)
    $taxaAmostragem = unpack('V', substr($cabecalho, 24, 4))[1];

    return $taxaAmostragem;
}



function converter_audio_para_mono($inputPath, $outputPath) {
    // Verifica se o ffmpeg está disponível no ambiente
    $ffmpegPath = 'C:/FFmpeg/bin/ffmpeg.exe'; // Se necessário, forneça o caminho absoluto para o ffmpeg
    if (!is_executable($ffmpegPath)) {
        die("ffmpeg não está disponível ou não é executável.");
    }

    // Comando para converter o áudio de estéreo para mono
    $command = "$ffmpegPath -i $inputPath -ac 1 $outputPath";

    // Executa o comando
    $output = null;
    $return_var = null;
    exec($command, $output, $return_var);

    // Verifica se houve algum erro
    if ($return_var !== 0) {
       // echo "Erro ao converter o arquivo para mono: " . implode("\n", $output) . "\n";
    } else {
       // echo "Arquivo convertido com sucesso para mono!\n";
    }
}







?>




</body>
</html>