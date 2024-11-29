<?php
// Define o caminho para o arquivo de log

  // $csvFilePath = "C:/audi/Processado/13-11_Metadados/Metadados-13-11-2024.csv";
  // $saida = "C:/audi/Processado/13-11_Metadados/mmetadados-13-11-2024.csv";
  


$caminnho ='C:\Projetos\audios_teste'; // Caminho vindo de um formulário ou outra entrada
$dia_atual = date("d-m-Y");

// Verifica se o caminho foi fornecido
if (empty($caminnho)) {
    echo "Digite um caminho válido!";
    exit;
} else {
    echo "Aguarde!!! Este processo pode demorar<br>";

    // Definir as variáveis de banco de dados
    $server = "172.22.4.108";
    $username = "linkedserver";
    $password = "Planejamento2022@@";
    $database = "bd_mis";

    // Conexão com o banco de dados usando PDO
    try {
        $pdo = new PDO("sqlsrv:Server=$server;Database=$database", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo "Erro de conexão: " . $e->getMessage();
        exit;
    }

    // Mensagens de consulta SQL
    $mensagens = [
        "SELECT * FROM TB_FULL_METADADOS_VIVO2 as A INNER JOIN tb_capacity CC WITH (NOLOCK) ON CC.Numero_Matricula = A.ID_AGENTE WHERE TIPO_FORMULARIO LIKE 'promessa%'",
        "SELECT * FROM TB_FULL_METADADOS_VIVO2 as A INNER JOIN tb_capacity CC WITH (NOLOCK) ON CC.Numero_Matricula = A.ID_AGENTE WHERE TIPO_FORMULARIO <> '' AND RW='1'"
    ];

    // Loop para exibir as mensagens duas vezes
    foreach ($mensagens as $query) {
        // Consulta SQL e execução
        $stmt = $pdo->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Diretório de saída
        $dia = date("d-m") . "_Metadados";  // Exemplo: 12-11_Metadados
        $dir = "$caminnho/Processado/$dia";

        // Criação do diretório se não existir
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        // Caminho do arquivo CSV dentro do diretório Processado
        $csvFilePath = "$dir/VivoMetadados-$dia_atual.csv";

        // Criação do arquivo CSV para escrita
        $file = fopen($csvFilePath, 'w');
        if ($file === false) {
            echo "Erro ao abrir o arquivo para escrita!";
            exit;
        }
        
        // Processamento das linhas e geração do CSV
        foreach ($rows as $row) {
            // Extração dos dados e manipulação
            $TEL = removerCaracteresNaoNumericos($row['TELEFONE']);
            $PLANTA = $row['PLANTA'];
            $CARTEIRAS = $row['CARTEIRAS'];
            $TIPO_PRODUTO = $row['TIPO_PRODUTO'];
            $TIPO_PESSOA = $row['TIPO_PESSOAS'];
            if ($TIPO_PRODUTO == "VIVOTECH") {
                $TIPO_PESSOA = "PJ";
            }
            $ID_LI = $row['NOME_AUDIO'];
            $GRUPOS = $row['GRUPOS'];
            //$TIPO_FORMULARIO = removerAspasDuplas($row['TIPO_FORMULARIO']); // Remover aspas duplas

            // Escreve os dados no CSV
            fputcsv($file, [
                $row['NOME_AUDIO'],
                $row['NOME_CLIENTE'],
                $row['NOME_EMPRESA'],
                $row['NOME_AGENTE'],
                $row['Login_vivo'],
                $row['DATA_CONTATO'],
                $row['HORA_CONTATO'],
                $row['CPF_CNPJ_CONTATO'],
                removerAspasDuplas($row['TIPO_FORMULARIO']),
                $row['TIPO_OPERACAO'],
                $TIPO_PESSOA,
                $CARTEIRAS,
                $TIPO_PRODUTO,
                $GRUPOS,
                $row['SUBGRUPO'],
                $row['FAIXA_ATRASO'],
                $row['SALDO_DEVEDOR'],
                $row['DURACAO'],
                $row['JORNADA'],
                $PLANTA,
                $TEL
            ]);

            // Deletando do banco se a consulta for do tipo 'promessa%'
            if ($query === $mensagens[0]) {
                $deleteQuery = "DELETE FROM TB_FULL_METADADOS_VIVO2 WHERE NOME_AUDIO = :id_li";
                $deleteStmt = $pdo->prepare($deleteQuery);
                $deleteStmt->execute(['id_li' => $ID_LI]);
            }
        }

        fclose($file);  // Fecha o arquivo CSV

        // Agora processa os arquivos de áudio
        $lines = file($csvFilePath, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $words = str_getcsv($line);
            $id = $words[0];
            $arquivo = "$caminnho/$id"; // Caminho do arquivo de áudio
            $backupDir = "$caminnho/Processado/$dia/";

            // Verifica se o arquivo de áudio existe
            if (file_exists($arquivo)) {
                // Cria o arquivo CSV de backup (Metadados-<dia>.csv) e escreve a linha
                $metadadosCsvPath = "$backupDir/Metadados-$dia_atual.csv";
                $metadadosFile = fopen($metadadosCsvPath, 'a'); // 'a' para append (adicionar ao final)
                if ($metadadosFile !== false) {
                    // Adiciona a linha no CSV
                    fputcsv($metadadosFile, [
                        $id,  // id do arquivo de áudio
                        $words[1], $words[2], $words[3], $words[4], removerAspasDuplas($words[5]), 
                        removerAspasDuplas($words[6]), removerAspasDuplas($words[7]), removerAspasDuplas($words[8]), removerAspasDuplas($words[9]), 
                        removerAspasDuplas($words[10]), $words[11], $words[12], $words[13], 
                        $words[14], $words[15], $words[16], $words[17], 
                        $words[18], $words[19], $words[20] // Completa com os dados da linha
                    ]);
                    fclose($metadadosFile); // Fecha o arquivo CSV

                    // Move o arquivo de áudio para o diretório de backup
                    copy($arquivo, $backupDir . $id);
                    // Exclui o arquivo original
                   unlink($arquivo);
                } else {
                    echo "Erro ao abrir o arquivo de metadados para adicionar a linha!";
                }
            } else {
               // echo "Arquivo de áudio não encontrado: $arquivo";
            }
        }
    }
    unlink("$dir/VivoMetadados-$dia_atual.csv");
     
  $arquivoEntrada = $metadadosCsvPath;
  $arquivoSaida = $dir."/Metadado-$dia_atual.csv"; // Caminho para o arquivo CSV de saída
  removerAspasDuplasDoCSV($arquivoEntrada, $arquivoSaida);
  
  unlink($arquivoEntrada);


    echo "Ligações da Vivo processadas com sucesso!";
}

// Função para remover caracteres não numéricos
function removerCaracteresNaoNumericos($input) {
    return preg_replace("/\D/", "", $input);
}

function removerAspasDuplas($input) {
    return preg_replace('/"/', '', $input); // Remove todas as aspas duplas
}
function removerAspasDuplasDoCSV($arquivoEntrada, $arquivoSaida) {
    // Verifica se o arquivo de entrada existe
    if (!file_exists($arquivoEntrada)) {
        echo "O arquivo de entrada não existe!";
        return false;
    }

    // Abre o arquivo de entrada para leitura
    $handleEntrada = fopen($arquivoEntrada, 'r');
    
    // Abre o arquivo de saída para escrita (ou cria um novo)
    $handleSaida = fopen($arquivoSaida, 'w');

    // Lê o arquivo linha por linha
    while (($linha = fgets($handleEntrada)) !== false) {
        // Remove as aspas duplas da linha
        $linhaSemAspas = str_replace('"', '', $linha);

        // Escreve a linha modificada no arquivo de saída
        fwrite($handleSaida, $linhaSemAspas);
    }

    // Fecha os arquivos
    fclose($handleEntrada);
    fclose($handleSaida);

    echo "Processamento concluído. O arquivo foi salvo em $arquivoSaida";
}


?>
