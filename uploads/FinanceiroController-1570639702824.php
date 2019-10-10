<?php

namespace app\controllers\financeiro;

use app\controllers\ContainerController;
use app\models\login\Login;
use app\models\financeiro\ContaBancaria;
use app\classes\Uri;
use app\classes\Redirect;
use app\models\report_mpdf\Report_mpdf;
use app\models\clients\Clients;
use app\models\financeiro\Financeiro;
use app\models\santander\Santander; 
use app\controllers\Db;
use app\models\users\Users;
use app\models\financeiro\ContaPagarReceber;
use app\models\financeiro\ContaAgendada;
use app\models\financeiro\CentroCusto;
use app\models\financeiro\Categoria;
use app\models\financeiro\Cnab\Factory;
use app\models\financeiro\Cnab\Banco;

class FinanceiroController extends ContainerController {

    public function __construct() {
        parent::__construct();
        Login::CheckAuth();

        # ----------------------------------------------------------------------
        # CRONS FINANCEIRO
        # ----------------------------------------------------------------------        
        ContaPagarReceber::marcarContasComoVencidas();
        ContaAgendada::criarContasAgendadas();
    }

    public function index() {
        $contestacao = ContainerController::executeSql("SELECT * FROM `contestacao`");
        $contaBancaria = new ContaBancaria();
        $contasPagarReceber = new ContaPagarReceber();
        $finModel = new Financeiro();
        $lista_contaBancaria = $contaBancaria->lista();
        $notas_pendentes = $finModel->RelacaoDeNotasPendentes();

        $vars = [
            'title' => 'Financeiro',
            'page_title' => 'Dashboard',
            'contestacao' => $contestacao,
            'lista_contaBancaria' => $lista_contaBancaria,
            'contasPagarReceber' => $contasPagarReceber,
            'notas_pendentes' => $notas_pendentes,
        ];

        $vars['nContas'] = floor(12 / count($lista_contaBancaria));

        $this->view($vars, 'financeiro.index');
    }

    // Adicionado pelo André --- Verifica se existe a cobraça na base
    public function fatauraTeste() {
        $this->view(
                [], 'financeiro.faturaTeste'
        );
    }

    public function ajaxconsulta($request) {
        unset($_POST["multiselect"]);
        $id = filter_input(INPUT_POST, 'idCobranca', FILTER_SANITIZE_SPECIAL_CHARS);
        $dadosConta = ContainerController::executeSql("SELECT 
                    cr.status,
                    cr.valor, 
                    DATE_FORMAT(STR_TO_DATE(cr.vencimento, '%Y-%m-%d'),  '%d/%m/%Y' ) as vencimento,
                    DATE_FORMAT(STR_TO_DATE(cr.emissao, '%Y-%m-%d'),  '%d/%m/%Y' ) as emissao,
                    cr.id,
                    cr.id_cliente,
                    cr.baixado_por,
                    cr.lancado_por,
                    cr.excluido_por,
                    cl.nome as nomeCliente,
                    bx.nome as baixado,
                    lc.nome as lancado,
                    dl.nome as deletado,
                    cr.obs
                    FROM `contas_receber` as cr
                    left join users as bx ON (cr.baixado_por = bx.id)
                    left join users as lc on (cr.lancado_por = lc.id)
                    left join users as dl on (cr.excluido_por = dl.id)
                    left join clientes as cl on (cr.id_cliente = cl.id)
                    WHERE cr.id = " . $id . "");
        $st = ContainerController::executeSql("SELECT `status` FROM `contas_receber` WHERE `id` = '" . $id . "'");

        $this->Tview([
            'dados' => $dadosConta[0],
            'style' => $st[0],
            'stt' => $mp,
            'title' => "Consulta",
            'nenhum' => count($dadosConta),
            'pp' => $id,
                ], 'financeiro.consultaBoleto');
    }

    public function gerar_remessa() {

        $financeiro = new Financeiro($db);
        $general = new General($db);

        $empresa = $general->dados_empresa();

        date_default_timezone_set('America/Sao_Paulo');

        $checkbox = $_POST["checkbox"];
        $tipo_remessa = $_POST["tipo"];
        $ids = implode(",", $checkbox);

        $pconta = reset($checkbox);
        $contaUnica = $financeiro->ContasReceber_one($pconta);

        $v_remessa = Form::selectSql($db, "SELECT * FROM remessa ORDER BY id DESC LIMIT 1");
        $numero_sequencial_arquivo = $v_remessa[0]->id + 1;


        if ($contaUnica->cod_banco == 104) {

            $idBanco = "104";
            $idArquivoRemessa = "cnab240_SIGCB";
            $codigo_cedente = $contaUnica->codigo_cedente;
        } else {
            $idBanco = "756";
            $idArquivoRemessa = "cnab400";
            $codigo_cedente = substr($contaUnica->codigo_cedente, 0, -1);
        }


        $receber = $financeiro->ContasReceber_varias($ids);


#########################################################
        $cod = date("dmyHi");
        $remessa = $financeiro->GerarCodigoRemessa($ids, $cod . "-" . $numero_sequencial_arquivo);
#########################################################


        if ($contaUnica->cod_banco == "104" || $contaUnica->cod_banco == "756") {

            $arquivo = new Remessa($idBanco, $idArquivoRemessa, array(
                'nome_empresa' => $empresa->razaosocial, // seu nome de empresa
                'tipo_inscricao' => 2, // 1 para cpf, 2 cnpj 
                'numero_inscricao' => trim(str_replace(array(".", " ", "-", "/"), "", $empresa->cnpj)), // seu cpf ou cnpj completo
                'agencia' => $contaUnica->agencia, // agencia sem o digito verificador 
                'agencia_dv' => $contaUnica->ag_digito, // somente o digito verificador da agencia 
                'conta' => $contaUnica->conta, // número da conta
                'conta_dac' => $contaUnica->ct_digito, // digito da conta
                'codigo_beneficiario' => $codigo_cedente, // codigo fornecido pelo banco
                'numero_sequencial_arquivo' => $numero_sequencial_arquivo,
                'situacao_arquivo' => 'P', // use T para teste e P para produçao
                'conta_dv' => $contaUnica->ct_digito, // digito da conta
                'codigo_beneficiario_dv' => $contaUnica->beneficiario_dv, // codigo fornecido pelo banco
            ));

            $lote = $arquivo->addLote(array('tipo_servico' => $contaUnica->nosso_numero_const1));


            while ($row_receber = $receber->fetch(PDO::FETCH_OBJ)) {

                $auth = $row_receber->pessoa;

                if ($auth == "Fisica" || $auth == "fisica") {
                    $numero_inscricao = str_replace(array(",", ".", "-", " ", "/"), "", $row_receber->cpf);
                    $tipo_inscricao = 1;
                } else {
                    $numero_inscricao = str_replace(array(",", ".", "-", " ", "/"), "", $row_receber->cnpj);
                    $tipo_inscricao = 2;
                }


                if ($numero_inscricao == NULL) {
                    echo "######################################################################";
                    echo $row_receber->nomeCliente . " NAO POSSUI CPF OU CNPJ<br>";
                    echo "######################################################################";
                    die;
                }
//echo $row_receber->id." | ".$row_receber->vencimento."</br>";
                $lote->inserirDetalhe(array(
                    'codigo_movimento' => $tipo_remessa,
                    'codigo_ocorrencia' => $tipo_remessa, //1 = Entrada de título, para outras opções ver nota explicativa C004 manual Cnab_SIGCB na pasta docs
                    'nosso_numero' => $row_receber->id, // numero sequencial de boleto  $row_receber->nosso.$row_receber->nosso2.str_pad($row_receber->id, 9, "0", STR_PAD_LEFT)
                    'seu_numero' => $row_receber->id, // se nao informado usarei o nosso numero 
                    /* campos necessarios somente para itau e siccob,  não precisa comentar se for outro layout    */
                    'carteira_banco' => "0", // codigo da carteira ex: 109,RG esse vai o nome da carteira no banco
                    'cod_carteira' => "01", // I para a maioria ddas carteiras do itau
                    'carteira' => 1,
                    'numero_contrato_dv' => "0",
                    /* campos necessarios somente para itau,  não precisa comentar se for outro layout    */
                    'especie_titulo' => "DM", // informar dm e sera convertido para codigo em qualquer laytou conferir em especie.php
                    'valor' => $row_receber->valor, // Valor do boleto como float valido em php
                    'emissao_boleto' => 2, // tipo de emissao do boleto informar 2 para emissao pelo beneficiario e 1 para emissao pelo banco
                    'protestar' => 3, // 1 = Protestar com (Prazo) dias, 3 = Devolver apos (Prazo) dias
                    'prazo_protesto' => 0, // Informar o numero de dias apos o vencimento para iniciar o protesto
                    'nome_pagador' => $general->formatarCaracter($row_receber->nomeCliente), // O Pagador ? o cliente, preste atenção nos campos abaixo
                    'tipo_inscricao' => $tipo_inscricao, //campo fixo, escreva '1' se for pessoa fisica, 2 se for pessoa juridica
                    'numero_inscricao' => $numero_inscricao, //cpf ou ncpj do pagador
                    'endereco_pagador' => $general->formatarCaracter($row_receber->endereco . " " . $row_receber->numero),
                    'bairro_pagador' => $row_receber->bairro,
                    'cep_pagador' => $general->formatar("cep", $row_receber->cep), // com hifem
                    'cidade_pagador' => $row_receber->cidade ? $row_receber->cidade : $contaUnica->cidade,
                    'uf_pagador' => $row_receber->estado ? $row_receber->estado : $contaUnica->estado,
                    'data_vencimento' => $row_receber->vencimento, // informar a data neste formato
                    'data_emissao' => date("Y-m-d"), // informar a data neste formato
//'data_desconto' => $row_receber->vencimento, // informar a data neste formato
//'vlr_desconto' => 0.05, // Valor do desconto
                    'baixar' => 1, // codigo para indicar o tipo de baixa '1' (Baixar/ Devolver) ou '2' (Nao Baixar / Nao Devolver)
                    'prazo_baixar' => $contaUnica->prazo, // prazo de dias para o cliente pagar ap?s o vencimento
//'mensagem' => $row_receber->obs,
                    'mensagem' => "JUROS de R$ $row_receber->juros ao dia. Multa de R$ $row_receber->multa" . PHP_EOL . "Não receber apos $contaUnica->prazo dias",
                    'email_pagador' => $row_receber->email, // data da multa
                    'codigo_carteira' => 1,
                    'com_registro' => 1,
                    /* JUROS */
                    /*
                      Código do Juros de Mora
                      Código adotado pela FEBRABAN para identificação do tipo de pagamento de juros de mora.
                      ‘1’ = Valor por Dia
                      ‘2’ = Taxa Mensal
                      ‘3’ = Isento
                     */
                    'codigo_juros' => $contaUnica->codigo_juros,
                    'vlr_juros' => str_replace(",", ".", $row_receber->juros), // Valor do juros de 1 dia'
                    'data_juros' => date('Y-m-d', strtotime($row_receber->vencimento . ' + 1 days')),
                    /* MULTA */
                    'codigo_multa' => $contaUnica->codigo_multa, //‘0’(Sem Multa); ou '1' (Valor Fixo); ou '2'(Percentual)
                    'data_multa' => date('Y-m-d', strtotime($row_receber->vencimento . ' + 1 days')), // informar a data neste formato, // data da multa
                    'vlr_multa' => str_replace(",", ".", $row_receber->multa), // valor da multa
// campos necessários somente para o sicoob
                    'taxa_multa' => 30.00, // taxa de multa em percentual
                    'taxa_juros' => 30.00, // taxa de juros em percentual
                ));

                $general->insertLog('cliente', "$row_receber->id_cliente", $_SESSION['username'], 'Envio de Remessa', "Registro de Remessa $row_receber->id", 'remessa');
            }
            $file = "$idBanco-$cod-" . $contaUnica->conta . "-" . $tipo_remessa . "-$numero_sequencial_arquivo.REM";

            $fpA0 = fopen("/tmp/" . $file, 'w');
            fwrite($fpA0, mb_convert_encoding($arquivo->getText(), 'windows-1252', 'utf-8'));
            fclose($fpA0);

            $post = array('arquivo' => $file, 'numero' => $cod . "-" . $numero_sequencial_arquivo, 'lote' => $ids, 'tipo' => $tipo_remessa);
            $gravar_remessa = Form::insertMySql($db, $post, 'remessa');

            if ($tipo_remessa == 2) {
                $update = Form::updateSqlSimple($db, "UPDATE contas_receber SET data_remessa=NULL, remessa=NULL WHERE id IN ($ids)");
            }

            foreach ($checkbox as $registro) {
                $financeiro->Contas_Receber_Log($_SESSION['user_id'], $_SESSION['username'], '', '', '', '', "Enviou Pedido de Registro Para o Banco", $registro);
            }

            download("/tmp/$file", $file);
        }
    }

    public function Contas_pagar_receber() {

        $this->view([
            'title' => 'contas a pagar e receber',
                ], 'financeiro.contas_pagar_receber');
    }

    public function select_modal_incluir_santander() {

        $this->view([
            'checkbox' => $_POST["checkbox"],
            'total' => count($_POST["checkbox"])
                ], 'financeiro.select_modal_incluir_santander');
    }

    public function IncluirSantander() {

//$receber = new Financeiro($db);
//$general = new General($db);
//$clientes = new Clientes($db);
//$clientes = new Clients;
        $financeiro = new Financeiro();
//$empresa = ContainerController::select("dadosempresa");
//$row_empresa = $empresa[0];

        $checkbox = $_POST["checkbox"];
        $array = array();

        foreach ($checkbox as $id) {

            $row_receber = $financeiro->ContasReceber_one($id);

            $auth = $row_receber->pessoa;

            if ($auth == "Fisica" || $auth == "fisica") {
                $numero_inscricao = str_replace(array(",", ".", "-", " ", "/"), "", $row_receber->cpf);
                $tipo_inscricao = "01";
            } else {
                $numero_inscricao = str_replace(array(",", ".", "-", " ", "/"), "", $row_receber->cnpj);
                $tipo_inscricao = "02";
            }

            $nosso = str_pad($row_receber->id, 12, "0", STR_PAD_LEFT);


            $parametros = array(
                "sistema" => 'YMB',
                "expiracao" => '100',
                "CONVENIO.COD-BANCO" => '0033',
                'CONVENIO.COD-CONVENIO' => trim($row_receber->codigo_cedente),
                "PAGADOR.TP-DOC" => $tipo_inscricao,
                "PAGADOR.NUM-DOC" => $numero_inscricao,
                'PAGADOR.NOME' => substr($row_receber->nomeCliente, 0, 40),
                'PAGADOR.ENDER' => strtoupper(substr($row_receber->endereco . ", " . $row_receber->numero, 0, 40)),
                'PAGADOR.BAIRRO' => trim(strtoupper(substr($row_receber->bairro, 0, 15))),
                'PAGADOR.CIDADE' => trim(strtoupper(substr($row_receber->cidade, 0, 15))),
                'PAGADOR.UF' => trim($row_receber->estado),
                'PAGADOR.CEP' => trim(str_replace(array(".", " ", "-", "/"), "", $row_receber->cep)),
                'TITULO.NOSSO-NUMERO' => trim($row_receber->nosso1 . $nosso),
                'TITULO.SEU-NUMERO' => $row_receber->id,
                'TITULO.DT-VENCTO' => trim(date('dmY', strtotime($row_receber->vencimento))),
                'TITULO.DT-EMISSAO' => date("dmY"),
                'TITULO.ESPECIE' => '02',
                'TITULO.VL-NOMINAL' => trim(str_replace(array(".", ",", " "), "", $row_receber->total)),
                'TITULO.PC-MULTA' => str_replace(array(".", ",", " "), "", $row_receber->multa),
                'TITULO.QT-DIAS-MULTA' => '01',
                'TITULO.PC-JURO' => str_replace(array(".", ",", " "), "", $row_receber->juros),
                'TITULO.TP-DESC' => '0',
                'TITULO.DT-LIMI-DESC' => '00000000',
                'TITULO.VL-ABATIMENTO' => '000',
                'TITULO.VL-DESC' => '000',
                'TITULO.TP-PROTESTO' => '0',
                'TITULO.QT-DIAS-PROTESTO' => '0',
                'TITULO.QT-DIAS-BAIXA' => $row_receber->prazo,
                'MENSAGEM' => $row_receber->obs_linha1,
            );

            $consulta = array(
                "CONVENIO.COD-BANCO" => '0033',
                'CONVENIO.COD-CONVENIO' => trim($row_receber->codigo_cedente),
                'TITULO.NOSSO-NUMERO' => trim($nosso),
            );
//dd($parametros);
//erros();
            $integracao = new Santander();
            $ticket = $integracao->boleto($parametros, "registraTitulo"); //"consultaTitulo"
            $data = json_decode($ticket);

            $situacao = $data->result->return->situacao;
            $linDig = $data->result->return->titulo->linDig;
            $cdBarra = $data->result->return->titulo->cdBarra;
            $descricaoErro = $data->result->return->descricaoErro;

            if ($situacao == "00") {
                ContainerController::updateAdvanced("contas_receber", array('data_remessa' => date("Y-m-d H:i:s"), "remessa" => 'WEBSERVICE', "n_documento" => $nosso, "codigobarras" => $cdBarra, "linhadigitavel" => $linDig, "remessa_entrada" => 1), array("id" => $id));
                ContainerController::insertAdvanced("user_log_acao", array("tipo_log" => "cliente", "id_cliente" => $row_receber->id_cliente, "usuario" => $_SESSION['username'], "acao" => "Registro de Fatura", "data" => date("Y-m-d H:i:s"), "ip" => $_SERVER['REMOTE_ADDR'], "obs" => "Registro de Fatura $id Webservice", "tipo" => "remessa"));

                ######################################### LOG DO USUARIO ##########################################################################################
                $log = ContainerController::AddLog("financeiro", "registroBoleto", "Registro Boleto Santander $id", json_encode($parametros));
                ###################################################################################################################################################
            }
            array_push($array, array("id" => $id, "msg" => $descricaoErro, "codigos_barras" => $cdBarra, "linha_digitavel" => $linDig, "cod" => $situacao, "dados" => $data->result));
        }

        echo json_encode($array);
    }

    public function show() {

//dd($_SESSION['uteis']);
    }

    public function FormBaixarPagamento($request) {

        $financeiro = new Financeiro();
        $id = $request->next;
        $f_categorias = $financeiro->f_categorias();
        $f_contasbancarias = $financeiro->contasbancarias();
        $f_centrocustos = $financeiro->centrocustos();
        $posicao = rand(1, 10);

        $f_contareceber = $financeiro->ContasReceber_one($id);


        $this->Tview([
            'dados' => $_POST,
            'id_cliente' => $request->parameter,
            'id' => $id,
            'username' => $_SESSION['username'],
            'categorias' => $f_categorias,
            'users' => $_SESSION['uteis']['users'],
            'contasbancarias' => $f_contasbancarias,
            'centrocusto' => $f_centrocustos,
            'contareceber' => $f_contareceber,
            'posicao' => $posicao,
                ], 'financeiro.baixar-pagamento');
    }

    public function FormEditarPagamento($request) {

        $financeiro = new Financeiro();
        $cliente = new Clients();

        $receber = $financeiro->ContasReceber_one($request->parameter);
        $contratos = $cliente->ClientContracts($receber->id_cliente);

        $this->Tview([
            'id' => $request->parameter,
            'receber' => $receber,
            'contrato' => $contratos,
            'metodoCobranca' => $_SESSION['uteis']['boletos'],
            'tipo_documento' => $_SESSION['uteis']['tipo_documento'],
                ], 'financeiro.editar-fatura');
    }

    public function fatura_edit($request) {

        $financeiro = new Financeiro();


        if (Permissao("financeiro_editar_data") == true) {
            $vencimento = filter_input(INPUT_POST, 'vencimento', FILTER_SANITIZE_SPECIAL_CHARS);
            $pdata = true;
        } else {
            $vencimento = filter_input(INPUT_POST, 'vencimento_old', FILTER_SANITIZE_SPECIAL_CHARS);
            $pdata = false;
        }

        $valor_novo = str_replace(",", ".", filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_SPECIAL_CHARS));
        $valor_old = str_replace(",", ".", filter_input(INPUT_POST, 'valor_old', FILTER_SANITIZE_SPECIAL_CHARS));
        $vencimento_old = date('Y-m-d', strtotime(str_replace("/", "-", $_POST['vencimento_old'])));

        if ($valor_novo < $valor_old && Permissao("financeiro_receber_abaixo_valor") == false) {
            $valor = $valor_old;
            $pvalor = false;
        } else {
            $valor = $valor_novo;
            $pvalor = true;
        }

        $array = array("vencimento" => date('Y-m-d', strtotime(str_replace("/", "-", $vencimento))), "valor" => $valor);
        $motivo = filter_input(INPUT_POST, 'motivo', FILTER_SANITIZE_SPECIAL_CHARS);

        $post = array_replace($_POST, $array);

        unset($post['vencimento_old']);
        unset($post['valor_old']);
        unset($post['motivo']);

//dd($post);

        /*
         * if ($row_receber->charge_id > 0 && $row_receber->n_parcela > 0) {
          $gerencianet = get_data($Url->baseUrl() . "gerencianet/update_carnet/" . $id);
          $result = $gerencianet . " CARNE";
          } else {
          $gerencianet = get_data($Url->baseUrl() . "gerencianet/update/" . $id);
          $result = $gerencianet . " BOLETO";
          }
         */

        $financeiro->ContasReceberLog($request->parameter, "Alteração", "Valor anterior era R$ $valor_old, para o Valor de R$ $valor. Vencimento anterior $vencimento_old para Vencimento $vencimento", $motivo);
        $data = ContainerController::updateAdvanced("contas_receber", $post, array("id" => $request->parameter));

        ######################################### LOG DO USUARIO ##########################################################################################
        $log = ContainerController::AddLog("financeiro", "alteracao", "Alteração de Fatura $request->parameter", json_encode($post));
        ###################################################################################################################################################

        echo json_encode(array("status" => $data, "permissao_data" => $pdata, "permissao_data" => $pvalor, "log" => $log));
    }

    public function BaixarFatura() {
        $financeiro = new Financeiro();
        $baixar = $financeiro->BaixarFatura();


        ######################################### LOG DO USUARIO ##########################################################################################
        $log = ContainerController::AddLog("financeiro", "alteracao", "Baixa de Fatura", json_encode($_POST));
        ###################################################################################################################################################
        echo json_encode($baixar);
    }

    public function excluirFatura() {
        $financeiro = new Financeiro();

        $result = $financeiro->ExcluirVariasContas();
        ######################################### LOG DO USUARIO ##########################################################################################
        $log = ContainerController::AddLog("financeiro", "excluir", "Exclusão de Boleto", json_encode($post));
        ###################################################################################################################################################
        echo json_encode($result);
    }

    public function EstonarPagamento() {

        if (Permissao("financeiro_estornar") == true) {
            $financeiro = new Financeiro();
            $result = $financeiro->EstornoVariasContas();
            ######################################### LOG DO USUARIO ##########################################################################################
            $log = ContainerController::AddLog("financeiro", "estornar", "Estorno de Boleto", json_encode($_POST));
            ###################################################################################################################################################
            jsonRender(array("permissao" => true, "result" => $result));
        } else {
            jsonRender(array("permissao" => false));
        }
    }

    public function reciclarFatura() {

        $financeiro = new Financeiro();
        $result = $financeiro->RestaurarVariasContas();

        ######################################### LOG DO USUARIO ##########################################################################################
        $log = ContainerController::AddLog("financeiro", "alteracao", "Restauração de Fatura", json_encode($_POST));
        ###################################################################################################################################################

        echo json_encode($result);
    }

    public function FaturaEmail() {

        $financeiro = new Financeiro();
        $result = $financeiro->EnviarEmail();
        ######################################### LOG DO USUARIO ##########################################################################################
        $log = ContainerController::AddLog("financeiro", "email", "Envio d Fatura por Email", json_encode($_POST));
        ###################################################################################################################################################
        echo json_encode($result);
    }

    public function ReciboPos($request) {

        $financeiro = new Financeiro();
        $receber = $financeiro->ContasReceber_one($request->parameter);

        $empresa = $_SESSION['uteis']['empresa'][0];
        $config = $_SESSION['uteis']['config'][0];
        $teste = $financeiro->dadosempresa;

        $hoje = date('Y-m-d');
        if ($receber->data_recebido_format == NULL) {
            $receber->data_recebido_format = $hoje;
        }
        /**/
        $this->Tview([
            'id' => $request->parameter,
            'dados' => $receber,
            'logo' => $config->logomarca,
            'razaosocial' => $empresa->razaosocial,
            'telefone' => $empresa->telefone,
            'email' => $empresa->email,
            'username' => $_SESSION['username'],
            'cpnj' => $empresa->cnpj,
            'rua' => $empresa->endereco,
            'cidade' => $empresa->cidade,
            'estado' => $empresa->estado,
            'bairro' => $empresa->bairro,
            'n' => $empresa->numero,
            "multa" => $multa,
            "juros" => $juros,
            "total" => $total,
            'cep' => $empresa->cep,
                ], 'financeiro.recibo_pos');
    }
    /**/

    public function Recibo($request) {

        $financeiro = new Financeiro();
        $receber = $financeiro->ContasReceber_one($request->parameter);

        $empresa = $_SESSION['uteis']['empresa'][0];
        $config = $_SESSION['uteis']['config'][0];


        $this->Tview([
            'id' => $request->parameter,
            'dados' => $receber,
            'logo' => $config->logomarca,
            'empresa' => $empresa,
            'nomefantasia' => $empresa->nomefantasia,
            'username' => $_SESSION['username'],
                ], 'financeiro.recibo');
    }

    public function nfe($request) {

        function limpar($nome) {

            $nome = trim($nome);

            $map = array(
                'á' => 'a',
                'à' => 'a',
                'ã' => 'a',
                'â' => 'a',
                'é' => 'e',
                'ê' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ô' => 'o',
                'õ' => 'o',
                'ú' => 'u',
                'ü' => 'u',
                'ç' => 'c',
                'Á' => 'A',
                'À' => 'A',
                'Ã' => 'A',
                'Â' => 'A',
                'É' => 'E',
                'Ê' => 'E',
                'Í' => 'I',
                'Ó' => 'O',
                'Ô' => 'O',
                'Õ' => 'O',
                'Ú' => 'U',
                'Ü' => 'U',
                'Ç' => 'C',
                'Ü' => 'U',
                '-' => '',
                '/' => '',
                '(' => '',
                ')' => '',
                ',' => '',
                '.' => '',
                '"' => '',
                ':' => '',
                'Í' => 'I',
                'ª' => '',
                'º' => '',
                '&' => ''
            );

            return strtoupper(strtr($nome, $map));
        }

        /*
          $financeiro = new Financeiro($db);
          $financas = new Financas($db);
          $general = new General($db);
          $arquivo = new GerarArquivos($db);
          $clientes = new Clientes($db);

          $configuracao = $general->configuracoes();
          $dados_empresa = $general->dados_empresa();


          $row_receber = $financeiro->ContasReceber_one($Url->returnID());

          $endereco = $clientes->EnderecoCobCliente($row_receber->id_cliente);
         */
        $financeiro = new Financeiro();
        $clientes = new Clients();

        $receber = $financeiro->ContasReceber_one($request->parameter);
        $endereco = $clientes->clienteEnderecoCob($receber->id_cliente);


        $this->Tview([
            'id' => $request->parameter,
            'row_receber' => $receber,
            'endereco' => $endereco,
                ], 'financeiro.nfe');
    }

    public function AddFatura($request) {

        $financeiro = new Financeiro();
        $clientes = new Clients();

        $f_categorias = $financeiro->f_categorias();
        $f_contasbancarias = $financeiro->contasbancarias();
        $f_centrocustos = $financeiro->centrocustos();


        $registros = $clientes->ClientId($request->parameter);
        $contratos = $clientes->ClientContracts($registros->id);
        $boletos = ContainerController::selectWhere("boleto", "id,nome", array("1" => 1), "nome ASC");
        $tipo_documento = \app\controllers\generic\GenericController::select("tipo_documento");
        $usuarios = ContainerController::selectWhere("users", "id,cname", array("status" => 1, 'deleted' => 0), "cname ASC");
        $cancelamento = ContainerController::selectWhere('reg_atendimento', 'protocolo', array("id_cliente" => $request->parameter));

        $this->Tview([
            'protocolo' => $cancelamento[0]->protocolo,
            'id_cliente' => $request->parameter,
            'contratos' => $contratos,
            'boleto' => $boletos,
            'tipo_documento' => $tipo_documento,
            'row_cliente' => $registros,
            'categorias' => $f_categorias,
            'contasbancarias' => $f_contasbancarias,
            'centrocusto' => $f_centrocustos,
            'usuarios' => $usuarios,
                ], 'financeiro.addFatura');
    }

    public function lancacamento_add($request) {
        $financeiro = new Financeiro();

        $post = filter_input_array(INPUT_POST);
        //dd($post);

        if (Permissao("financeiro_adicionar") == true) {
            $data = $financeiro->calcularParcelas($post['parcela'], $post['vencimento'], $post['Dparcela'], $post['Pparcela'], $post['id'], $post['contaB'], $post['ODparcela'], $post['tipo'], $post['plano'], $post['OPparcela']);
            $financeiro->ContasReceberLog($post['parcela'], "Inclusão", "Valor  R$ $valor, Parcelas " . $post['parcela'] . "Vencimento " . $post['vencimento']);
        } else {
            
        }

        echo json_encode(array("status" => $data['status'], "permissao" => Permissao("financeiro_adicionar"), "fatura" => $data['faturas'], "financeiro" => $data['financeiro']));
    }

    public function fatura_add($request) {

        $financeiro = new Financeiro();

        //$vencimento = date('Y-m-d', strtotime(str_replace("/", "-", $_POST['vencimento'])));
        //$array = array("vencimento" => $vencimento, "valor" => $valor);
        //$post = array_replace($_POST, $array);
        //$timestamp = $general->get_nextDay($row_cliente['vencimento']);
        //$vencimento = date('Y-m-d', $timestamp);

        $id = $request->parameter;
        $id_conta = filter_input(INPUT_POST, 'conta', FILTER_SANITIZE_SPECIAL_CHARS);

        $valor = str_replace(",", ".", filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_SPECIAL_CHARS));
        $valorp = str_replace(",", ".", filter_input(INPUT_POST, 'valorp', FILTER_SANITIZE_SPECIAL_CHARS));

        $obs = filter_input(INPUT_POST, 'obs', FILTER_SANITIZE_SPECIAL_CHARS);
        $obsp = filter_input(INPUT_POST, 'obsp', FILTER_SANITIZE_SPECIAL_CHARS);
        $tipo_documento = filter_input(INPUT_POST, 'tipo_documento', FILTER_SANITIZE_SPECIAL_CHARS);
        $carne_unica = 1;
        $emissao = date("Y-m-d");
        $n_parcela = filter_input(INPUT_POST, 'n_parcela', FILTER_SANITIZE_SPECIAL_CHARS);
        $contrato = filter_input(INPUT_POST, 'contrato', FILTER_SANITIZE_SPECIAL_CHARS);
        $parcela = 1;
        $prorata = $_POST['prorata'];
        $negociada = $_POST['negociada'];
        $condicao = filter_input(INPUT_POST, 'condicao', FILTER_SANITIZE_SPECIAL_CHARS);

        if (filter_input(INPUT_POST, 'vencimento', FILTER_SANITIZE_SPECIAL_CHARS) == "") {
            $venc_data = date("Y-m-d");
        } else {
            $venc_data = date('Y-m-d', strtotime(str_replace("/", "-", filter_input(INPUT_POST, 'vencimento', FILTER_SANITIZE_SPECIAL_CHARS))));
        }


        $post = filter_input_array(INPUT_POST);
        $f_id_categoria = $post['id_categoria'];
        $f_id_centrocusto = $post['id_centrocusto'];
        $f_id_contabancaria = $post['id_contabancaria'];
        $f_id_tecnico = $post['tecnico_responsavel'];

        $array = array("vencimento" => $venc_data, "valor" => $valor);
        $post_data = array_replace($post, $array);

        $vencimento = date('Y-m-d', strtotime(str_replace("/", "-", $venc_data)));
        $dataVencimento = date("Y-m-d", strtotime("+" . (2 - 1) . " month", mktime(0, 0, 0, date('m', strtotime($vencimento)), date('d', strtotime($vencimento)), date('Y', strtotime($vencimento)))));


        if (Permissao("financeiro_adicionar") == true) {
            $data = $financeiro->calcularParcelas($n_parcela, $venc_data, $valor, $prorata, $id, $id_conta, $obs, $tipo_documento, $carne_unica, $contrato, $negociada, $valorp, $obsp, $condicao, $f_id_categoria, $f_id_centrocusto, $f_id_contabancaria, $f_id_tecnico);
            $financeiro->ContasReceberLog($request->parameter, "Inclusão", "Valor  R$ $valor, Parcelas $n_parcela . Vencimento $vencimento", '');
        }

        echo json_encode(array("status" => $data['status'], "permissao" => Permissao("financeiro_adicionar"), "fatura" => $data['faturas'], "financeiro" => $data['financeiro']));
    }

    public function VerificaRemessaBoleto1($request) {

        //erros();

        $financeiro = new Financeiro();

        echo $financeiro->VerificaRemessaBoleto($request->parameter);
    }

    /**
     * -------------------------------------------------------------------------
     * TIPOS DE CATEGORIAS
     * -------------------------------------------------------------------------
     */
    public function tipo_categoria() {

        $financeiro = new Financeiro();
        $this->view([
            'title' => 'Tipos de Categorias',
            'dados' => $financeiro->tipo_categoria_lista(),
                ], 'financeiro.cadastro.tipo_categoria_lista');
    }

    public function to_datatable_tipo_categoria() {


        $tabela = New Financeiro();
        $tabela->to_datatable_tipo_categoria();
    }

    public function tipo_categoria_form($request) {
        $this->view([
            'title' => 'Tipos de Categorias',
            'dados' => Db::getRow('f_categorias_tipos', '*', 'id=:id', ['id' => $request->next]),
                ], 'financeiro.cadastro.tipo_categoria_form');
    }

    public function tipo_categoria_salvar() {
        $post = filter_input_array(INPUT_POST);

        $financeiro = new Financeiro();

        if ($financeiro->tipo_categoria_salvar($post)) {
            echo 'Dados salvo com sucesso!';

            $this->notifications("success", "Categoria", "Categoria salva com sucesso.");
            Redirect::redirect("/financeiro/tipo_categoria");
        } else {
            echo 'Ocorreu um erro!';

            $this->notifications("error", "Categoria", "Erro ao salvar essa Categoria!");
            Redirect::redirect("/financeiro/tipo_categoria");
        }
    }

    public function tipo_categoria_excluir($request) {
        $financeiro = new Financeiro();
        $financeiro->tipo_categoria_excluir($request->next);

        $this->notifications("success", "Categoria", "Categoria excluida com sucesso!");
        Redirect::redirect("/financeiro/tipo_categoria");
    }

    public function tipo_categoria_contas_excluir($request) {
        $financeiro = new Financeiro();
        $financeiro->tipo_categoria_contas_excluir($request->next);
    }

    /**
     * -------------------------------------------------------------------------
     * CATEGORIA DAS CONTAS
     * -------------------------------------------------------------------------
     */
    public function categorias_contas() {
        $financeiro = new Categoria();

        $this->view([
            'title' => 'Tipo de Contas',
            'dados' => $financeiro->lista(),
                ], 'financeiro.cadastro.categorias_contas');
    }

    public function insert_tipo_categoria_contas() {
        $post = filter_input_array(INPUT_POST);

        $financeiro = new Categoria();

        if ($financeiro->adicionar($post)) {
            $this->notifications("success", "Categoria", "Categoria salva com sucesso.");
            Redirect::redirect("/financeiro/categorias_contas");
        } else {
            $this->notifications("error", "Categoria", "Erro ao salvar essa Categoria!");
            Redirect::redirect("/financeiro/categorias_contas");
        }
    }

    public function delete_tipo_categoria_contas($request) {

        $financeiro = new Categoria();
        $financeiro->excluir($request->parameter);

        $this->notifications("success", "Categoria", "Categoria excluida com sucesso!");
        Redirect::redirect("/financeiro/categorias_contas");
    }

    public function tipo_categoria_contas_form($request) {
        $financeiro = new Financeiro();
        $this->view(
                ['title' => 'Categorias de Contas',
            'data' => Db::getRow('f_categorias', '*', 'id=:id', ['id' => $request->next]),
            'dados' => $financeiro->tipo_categoria_lista(),
                ], 'financeiro.cadastro.tipo_categoria_contas_form');
    }

    public function to_datatable_categoria_contas() {

        $tabela = New Categoria();
        $tabela->to_datatable_categoria_contas();
    }

    /**
     * -------------------------------------------------------------------------
     * CONTAS BANCARIAS
     * -------------------------------------------------------------------------
     */
    public function contas_bancarias() {
        $this->view([
            'title' => 'Contas Bancarias',
                ], 'financeiro.contas_bancarias.index');
    }

    public function contas_bancarias_excluir($request) {
        $model = new ContaBancaria();
        $model->excluir($request->next);
    }

    public function form_modal_contas_bancarias($request) {
        $model = new ContaBancaria();

        $this->view([
            'title' => 'Contas Bancarias',
            'dados' => $model->getRow($request->next)
                ], 'financeiro.contas_bancarias.modal.form_modal_contas_bancarias');
    }

    public function form_modal_clientesSCob($request) {
        $model = new Financeiro();
        $dados = $model->getclientesSCob($request->next);
        $date = date("m/Y");

        $date = $dados->vencimento . "/" . $date;
        $contas = ContainerController::select(boleto);
        $contrato = ContainerController::selectWhere(contrato, "*", array("id_cliente" => $request->parameter));

        $this->view([
            'title' => 'Contas Bancarias',
            'dados' => $dados,
            'vencimento' => $date,
            'contas' => $contas,
            'contrato' => $contrato,
                ], 'financeiro.clientesSCob.modal');
    }

    public function insert_contas_bancarias() {

        $post = filter_input_array(INPUT_POST);

        $financeiro = new ContaBancaria();

        if ($post['id'] > 0) {
            $financeiro->alterar($post['id'], $post);
        } else {

            $financeiro->adicionar($post);
        }
    }

    public function to_datatable() {

        $tabela = New Financeiro();
        $tabela->to_datatable();
    }

    public function to_datatable_faturas($request) {
        $financeiro = new Financeiro();

        $financeiro->to_datatable_faturas($request->parameter);
    }

    /**
     * -------------------------------------------------------------------------
     * CONTAS A PAGAR E RECEBER
     * -------------------------------------------------------------------------
     */
    public function contas_pagar_receber_fm($request) {

        $get = filter_input_array(INPUT_GET);

        $financeiro = new ContaPagarReceber();
        $contas_bancarias = new ContaBancaria();
        $form = $financeiro->getRow($request->parameter);
        $categ = new Categoria();
        $rowCategoria = $categ->getRow($form->id_categoria);

        $this->view([
            'title' => 'Contas a Pagar/Receber',
            'data' => $form,
            'listaPeriodicidade' => ContaAgendada::periodicidades(),
            'rowCategoria' => $rowCategoria,
            'contas' => $contas_bancarias->lista(),
            'tipos_cliente' => ContaPagarReceber::clientes_tipo(),
            'check_box' => $financeiro->tipoContas(),
            'check_box2' => $financeiro->situacoes(),
            'get' => $get
                ], 'financeiro.contas_pagar_receber.contas_pagar_receber_fm');
    }

    public function contas_pagar_receber_salvar() {
        $post = filter_input_array(INPUT_POST);
        $financeiro = new ContaPagarReceber();


        if ($post['acaoDividirDuplicarValor'] != 'nenhuma' and $post['acaoDividirDuplicarValor'] != 'agendar') {
            $return = $financeiro->DividirDuplicarConta($post);
        } else {
            if ($post['id'] > 0) {
                $id = $post['id'];
                $return = $financeiro->alterar($id, $post);
            } else {
                $return = $financeiro->adicionar($post);
                $id = $return;
            }

            # salvar arquivo de nota
            if ($_FILES['arquivo_nota']['size'] > 0) {
                $financeiro->salvarArquivoConta('arquivo_nota', $id);
            }
        }

        # criar agendamento ----------------------------------------------------
        if ($post['acaoDividirDuplicarValor'] == 'agendar') {
            $contaAgendada = new ContaAgendada();
            $contaAgendada->adicionar($post);
        }


        ######################################### LOG DO USUARIO ##########################################################################################
        $log = ContainerController::AddLog("financeiro", "cadastro", "Cadastro de Fatura", json_encode($post));
        ###################################################################################################################################################


        $this->notifications("success", "CONTAS PAGAR/RECEBER", "Conta salva com sucesso");
        Redirect::redirect("/financeiro/contas_pagar_receber_fm");
    }

    public function mudarSituacaoConta($request) {

        $id = $request->next;
        $sql = "UPDATE f_contas_pagar_receber SET checado = NOT checado WHERE id=:id";
        Db::updateSql($sql, ['id' => $id]);

        $row = Db::getRow("f_contas_pagar_receber", 'checado', 'id=:id', ['id' => $id]);
        echo $row->checado * 1;
    }

    public function delete_contas_pagar($request) {
        $delete = new ContaPagarReceber();
        $delete->excluir($request->parameter);
        ######################################### LOG DO USUARIO ##########################################################################################
        $log = ContainerController::AddLog("financeiro", "excluiu", "Exclusão de Faturas", json_encode($_POST));
        ###################################################################################################################################################
    }

    public function ClienteFornecedorDetalhes() {
        ;
        $id = filter_input(INPUT_GET, "id_cliente");

        $cliente_tipo = filter_input(INPUT_GET, "cliente_tipo");

        $this->view(['title' => 'Detalhes',
            'row' => ContaPagarReceber::ClienteFornecedorDetalhes($id, $cliente_tipo),
            'tipo_cliente' => $cliente_tipo
                ]
                , 'financeiro.contas_pagar_receber.tabela_detalhes');
    }

    private function _contasPagarReceberPadrao($tipo_contas) {
        $get = filter_input_array(INPUT_GET);
        $contasPagarReceber = new ContaPagarReceber();
        $contaBancaria = new ContaBancaria();

        if (!isset($get['dt_vencimento_de'])) {
            $get['dt_vencimento_de'] = date("Y-m-01");
        }

        if (!isset($get['dt_vencimento_ate'])) {
            $get['dt_vencimento_ate'] = date("Y-m-t");
        }

        return [
            'title' => 'Contas a Pagar',
            'situacoes' => $contasPagarReceber->situacoes(),
            'tipos_cliente' => ContaPagarReceber::clientes_tipo(),
            'contaBancaria_lista' => $contaBancaria->lista(),
            'get' => $get,
        ];
    }

    public function contas_pagar() {
        $this->view($this->_contasPagarReceberPadrao("PAGAR"), 'financeiro.contas_pagar_receber.contas_pagar');
    }

    public function contas_receber() {
        $vars = $this->_contasPagarReceberPadrao("RECEBER");
        $vars['title'] = 'Contas a Receber';
        $this->view($vars, 'financeiro.contas_pagar_receber.contas_receber');
    }

    public function contas_pagar_receber_imprimir() {
        $pdf = new Report_mpdf();
        $get = filter_input_array(INPUT_GET);
        $tabela = New ContaPagarReceber();
        $lista = $tabela->lista($get['tipo'], $get);

        $vars = [
            'lista' => $lista,
        ];

        $body = "<h3 class='text-center'>RELATÓRIO DE CONTAS A " . $get['tipo'] . "</h3>";
        $body .= $this->view($vars, 'financeiro.contas_pagar_receber_imprimir', true);

        $pdf->setBody($body);
        $pdf->BuildPDF();
    }

    public function to_datatable_contaspagarreceber() {
        $get = filter_input_array(INPUT_GET);
        $tabela = New ContaPagarReceber();
        $tabela->to_datatable($get['tipo'], $get);
    }

    public function relatorio_fluxo_de_conta() {
        $get = filter_input_array(INPUT_GET);
        $contaBancaria = new ContaBancaria();

        $dtNow = new \DateTime();
        $dtInicio = $dtNow->sub(new \DateInterval("P7D"));

        if (!isset($get['dt_de'])) {
            $get['dt_de'] = $dtInicio->format('Y-m-d');
            $get['dt_ate'] = date("Y-m-t");
        }

        if (!isset($get['id_contabancaria'])) {
            $get['id_contabancaria'] = 6;
        }

        $vars = [
            'title' => 'Relatorio Fluxo de Conta',
            'get' => $get,
            'contaBancaria_lista' => $contaBancaria->lista(),
        ];

        $this->view($vars, 'financeiro.contas_pagar_receber.relatorio_fluxo_de_conta');
    }

    public function relatorio_fluxo_conta_table() {

        $conta = new ContaPagarReceber();
        $get = filter_input_array(INPUT_POST);

        $fluxoConta = $conta->fluxoConta($get);

        $vars = [
            'title' => 'Relatorio Fluxo de Conta',
            'lista' => $fluxoConta['lista'],
            'saldoAnterior' => $fluxoConta['saldoAnterior'],
        ];

        $this->view($vars, 'financeiro.contas_pagar_receber.relatorio_fluxo_de_conta_table');
    }

    public function transferencia_entre_contas() {


        $this->view([
            'title' => 'Transfêrencia entre contas '
                ], 'financeiro.contas_pagar_receber.transferencia_entre_contas');
    }

    public function contas_agendadas() {
        $get = filter_input_array(INPUT_GET);
        $conta = new ContaPagarReceber();
        $contaBancaria = new ContaBancaria();

        $vars = [
            "title" => "Contas Agendadas",
            'get' => $get,
            'tiposContas' => $conta->tipoContas(),
            'tiposClientes' => $conta->clientes_tipo(),
            'contaBancaria_lista' => $contaBancaria->lista(),
        ];

        $this->view($vars, 'financeiro.contas_pagar_receber.contas_agendadas');
    }

    public function servico_tecnicos() {

        $get = filter_input_array(INPUT_GET);
        $model = new ContaPagarReceber();
        $users = new Users();
        $registros = $model->servicosTecnicos($get);

        if (!isset($get['dt_pagamento_de'])) {
            $get['dt_pagamento_de'] = date("Y-m-01");
            $get['dt_pagamento_ate'] = date("Y-m-t");
        }

        $vars = [
            "title" => "Serviços dos Técnicos",
            'lista' => $registros,
            'tecnicos' => $users->usuario_suporte(),
            'get' => $get,
        ];

        $this->view($vars, 'financeiro.contas_pagar_receber.servicos_tecnicos');
    }

    public function servico_tecnicos_salvar() {

        $acao = filter_input(INPUT_GET, 'acao');
        $post = filter_input_array(INPUT_POST);
        $model = new ContaPagarReceber();

        foreach ($post['id'] as $id) {
            $model->servicosTecnicosSalvarComoPago($id, $acao);
        }

        $this->notifications("success", "SERVIÇOS TÉCNICOS", "Os registros selecionados foram salvos.");
    }

    public function spc() {

        $this->view(["title" => "Spc"], "financeiro.contas_pagar_receber.spc");
    }

    public function metodos_pagamentos() {

        $this->view(["title" => "Metodos Pagamentos"], "financeiro.contas_pagar_receber.metodos_pagamentos");
    }

    public function centro_custo_dropdown() {
        $centroCusto = new CentroCusto();
        $get = filter_input_array(INPUT_GET);
        $result = $centroCusto->dropDown($get['q'], $get['id']);
        $arr = array();
        foreach ($result as $idRow => $nome) {
            $arr[] = array('id' => $idRow, 'text' => $nome);
        }
        jsonRender($arr);
    }

    public function categorias_dropdown() {
        $model = new Categoria();
        $get = filter_input_array(INPUT_GET);
        $result = $model->dropDown($get['q'], $get['id'], $get['categorias_tipos']);
        $arr = array();
        foreach ($result as $idRow => $nome) {
            $arr[] = array('id' => $idRow, 'text' => $nome);
        }
        jsonRender($arr);
    }

    public function categoriasTipos_dropdown() {
        $model = new \app\models\financeiro\CategoriaTipo();
        $get = filter_input_array(INPUT_GET);
        $result = $model->dropDown($get['q'], $get['id']);
        $arr = array();
        foreach ($result as $idRow => $nome) {
            $arr[] = array('id' => $idRow, 'text' => $nome);
        }
        jsonRender($arr);
    }

    public function clientes_dropdown() {
        $cliente_tipo = filter_input(INPUT_GET, 'cliente_tipo');
        $id = filter_input(INPUT_GET, 'id');
        $term = filter_input(INPUT_GET, 'q'); // termo para limitar a busca
        $conta = new ContaPagarReceber();

        $lista = $conta->dropDownClienteFornecedor($cliente_tipo, $term, $id);

        $arr = array();

        foreach ($lista as $idRow => $nome) {
            $arr[] = array('id' => $idRow, 'text' => $nome);
        }

        jsonRender($arr);
    }

    /**
     * -------------------------------------------------------------------------
     * CONSULTAS
     * -------------------------------------------------------------------------
     */
    public function consulta_txt() {

        $this->view(["title" => "Consulta Avançada"], "financeiro.consultas.consultas_txt");
    }

    public function insert_datatable() {
        //erros(); 
        //dd(filter_input_array(INPUT_GET));
        $card = New Financeiro();
        $card->insert_datatable(filter_input_array(INPUT_GET));
    }

    public function checkBoxConsultaTxt() {
        $get = (filter_input_array(INPUT_GET));
        echo $get[dados];
    }

    public function consulta_nfe() {

        $this->view(["title" => "Consulta Nfe"], "financeiro.consultas.consultas_nfe");
    }

    public function consulta_extrato() {

        $this->view(["title" => "Consulta Extrato"], "financeiro.consultas.consultas_extrato");
    }

    public function consulta_faturas() {

        $this->view(["title" => "Consulta Faturas Recebidas "], "financeiro.consultas.consultas_faturas");
    }

    public function consulta_remessas() {
        $this->view(["title" => "Consultar Remessa"], "financeiro.consultas.consultas_remessas");
    }

    public function pagamentos_duplicados() {
        $this->view(["title" => "Pagamentos Duplicados"], "financeiro.consultas.pagamentos_duplicados");
    }

    //Gerar Cartão
    public function gerarCartao() {

        if (Permissao("cartao_seguranca") == true) {
            $this->view(
                    ["title" => 'Cartão de Segurança'], "financeiro.cartaoSeg.cartaoSeguranca"
            );
        } else {
            header("Location:/financeiro");
        }
    }

    public function imprmirCartao($request) {
        if (Permissao("cartao_seguranca") == true) {
            $pesquisa = ContainerController::executeSql("SELECT * FROM `f_cartao_seguranca` WHERE `id_usuario` = '" . $_SESSION['user_id'] . "'");
            $data_hoje = date('d/m/Y');
            $this->view(
                    [
                "title" => 'Imprimir Cartão',
                'data_hoje' => $data_hoje,
                "dados" => $pesquisa[0]
                    ], 'financeiro.cartaoSeg.imprimirCartao'
            );
        } else {
            header('Location:/financeiro');
        }
    }

    public function to_datatable_cartao($request) {

        if (Permissao("cartao_seguranca") == true) {
            $card = New Financeiro();
            $card->to_datatable_cartao($request);
        } else {
            header("Location/financeiro");
        }
    }

    public function gerandoCartao($request) {
        $this->view(['title' => 'Gerando Novo Cartão', 'id' => $request->parameter], 'financeiro.cartaoSeg.gerarNovoCartao');
    }

    public function gerarNovoCartao($request) {
        if (Permissao("cartao_seguranca") == true) {
            $post = filter_input_array(INPUT_POST);

            $array = array(1 => "um", 2 => "dois", 3 => "tres", 4 => "quatro",
                5 => "cinco", 6 => "seis", 7 => "sete", 8 => "oito",
                9 => "nove", 10 => "dez"
            );
            for ($i = 1; $i <= 10; $i++) {
                $a = "";
                for ($j = 1; $j <= 4; $j++) {
                    $a = $a . rand(0, 9);
                }

                $update = ContainerController::updateAdvanced('f_cartao_seguranca', array($array[$i] => $a), array('id' => $post['id']));
            }
            $hoje = date('d-m-Y');
            $hoje2 = date('Y-m-d H:i:s');
            $validade = date('Y-m-d', strtotime("+" . $post['validade'] . "days", strtotime($hoje)));

            $updateData = ContainerController::updateAdvanced('f_cartao_seguranca', array('data' => $hoje2), array('id' => $post['id']));
            $alterarValidade = ContainerController::updateAdvanced('f_cartao_seguranca', array('validade_cartao' => $validade), array('id' => $post['id']));

            ######################################### LOG DO USUARIO ##########################################################################################
            #$log = ContainerController::AddLog("financeiro", "gerarCartao", "Gerar Cartão", json_encode($_POST));
            ###################################################################################################################################################
            header('Location:/financeiro/gerarCartao');
        } else {
            header("Location/financeiro");
        }
    }

    //Fim Gerar Cartão


    public function gerarSici() {
        $this->view(["title" => "Gerar Sici"], "financeiro.consultas.gerarSici");
    }

    public function cron() {
        ContaAgendada::criarContasAgendadas();
        ContaPagarReceber::marcarContasComoVencidas();
    }

    public function estornar($request) {
        dd($request);
        $this->view([
            "title" => 'Estornar'
                ], "financeiro.estornar");
    }

    /*     * *
     * * UPLOAD **      
     * * */

    public function retorno_new($request) {
        //erros();
        $db = 'retorno';
        //require "/usr/local/nexus-mvc/vendor/autoload.php";

        $cnabFactory = new \Cnab\Factory();
        $cnabBanco = new \Cnab\Banco();
        $finModel = new Financeiro();

        $finContRec = new ContaPagarReceber();

        $nome = $_FILES['arquivo']['name'];
        $type = $_FILES['arquivo']['type'];
        $size = $_FILES['arquivo']['size'];
        $tmp = $_FILES['arquivo']['tmp_name'];
        $pasta = $_SERVER['DOCUMENT_ROOT'] . "/uploads";

        echo "$nome, $tmp, $pasta";
        $date = ContainerController::selectWhere('retorno', "*", array('retorno' => $nome));

        if (count($date) == 0) {
            echo "<br> Cadastrar. <br> <hr> <br>";

            $conteudo = file_get_contents($tmp);
            $post = array("retorno" => $nome, 'user_id' => $_SESSION['user_id']);
            $cadastrar = ContainerController::insertAdvanced($db, $post);
            move_uploaded_file($tmp, $pasta . "/" . $nome);
            $idRetorno = $cadastrar;
        } else {
            echo "<br> Ja cadastrado. <br> <hr> <br>";
            $conteudo = file_get_contents($tmp);
            $idRetorno = $date[0]->id;
        }
        $id_user_retorno = $date[0]->user_id;
        //$cadastradoPor = $finModel->cadPor($id_user_retorno);
        //dd($cadastradoPor->cname);

        $arquivo = $cnabFactory->createRetorno($pasta . "/" . $nome);
        $detalhes = $arquivo->listDetalhes();

        $detalhe_conta = $arquivo->getConta();


        $banco = $cnabBanco->getBanco($arquivo->getCodigoBanco());
        $digito = $arquivo->getContaDac();
        $conta_completa = $detalhe_conta . $digito;
        $idConta = $finModel->idConta($conta_completa);
        $idConta = $idConta->id;


        /* DADOS DE CONTAGEM */
        $arr = array();
        $arrNove = array();
        $mt = 0; //Valor a pagar de multa !
        $duplicatas = 0;
        $registradas = 0;
        $liquidacao = 0;
        $montante = 0;
        $tf = 0; // Tarifa
        $spc = 0;
        $arrSpc = array();
        $negociados = 0;
        $arrNegociados = array();
        $bank = $finModel->pegaBanco(); // PEGA DADOS DO CENTRO DE CUSTO E DO BANCO.


        foreach ($detalhes as $indice => $detalhe) {


            $nossoNumero = $detalhe->getNossoNumero();
            $valorRecebido = number_format($detalhe->getValorRecebido(), 2, '.', ',');
            $valorPago = $detalhe->getValorPago();
            $dataPagamento = $detalhe->getDataOcorrencia();
            $carteira = $detalhe->getCarteira();
            $dataCredito = $detalhe->getDataCredito();
            $dataCredito = $dataCredito->format('d/m/Y');
            $AgenciaCobradora = $detalhe->getAgenciaCobradora();
            $codNome = $detalhe->getCodigoNome();

            $valor_titulo = $detalhe->getValorTitulo();
            $baixa = $detalhe->isBaixa();
            $rejeitada = $detalhe->isBaixaRejeitada();
            $abatimentos = $detalhe->getValorAbatimento();
            $descontos = $detalhe->getValorDesconto();
            $documento = $detalhe->getNumeroDocumento();
            $getValorMoraMulta = $detalhe->getValorMoraMulta();
            $getValorTitulo = $detalhe->getValorTitulo();
            $getValorRecebido = $detalhe->getValorRecebido();
            $getValorTarifa = $detalhe->getValorTarifa();
            $getValorOutrosCreditos = $detalhe->getValorOutrosCreditos();
            $getValorPago = $detalhe->getValorPago();
            $getAlegacaoPagador = $detalhe->getAlegacaoPagador();

            $multa = number_format($detalhe->getValorMoraMulta(), 2, '.', ',');
            $tarifa = number_format($detalhe->getValorTarifa(), 2, '.', ',');
            $liquido = number_format(($valor_titulo + $multa) - $tarifa, 2);

            $t_num_doc_cob = $detalhe->getNumeroDocumento();
            $cod = $detalhe->getCodigo();

            $row_receber = $finModel->retorno1($nossoNumero);

            if ($cod == 6) {
                //Liquidaçao

                $arr[$nossoNumero]['nDocumento'] = $t_num_doc_cob;
                $arr[$nossoNumero]['nome'] = $row_receber->nome;
                $arr[$nossoNumero]['tarifa'] = $tarifa;
                $arr[$nossoNumero]['multa'] = $multa;
                $arr[$nossoNumero]['numero'] = $nossoNumero;
                $arr[$nossoNumero]['valor'] = $valorRecebido;
                $arr[$nossoNumero]['dataPagamento'] = $dataCredito;
                //$arr[$nossoNumero]['negociado'] = $verificaNegociados;

                /*

                  $dadosConta = $finModel->ret($nossoNumero);

                  if ($dadosConta->spc == 1) {
                  $arr[$nossoNumero]['spc'] = 1;
                  $spc = $spc + 1;
                  array_push($arrSpc, $nossoNumero);
                  } else {
                  $arr[$nossoNumero]['spc'] = 0;
                  }

                  if ($dadosConta->negociada == 1) {
                  $arr[$nossoNumero]['negociado'] = 1;
                  $negociados = $negociados + 1;
                  array_push($arrNegociados, $nossoNumero);
                  } else {
                  $arr[$nossoNumero]['negociado'] = 0;
                  }
                 */

                /*
                  //CADASTRA TODAS AS CONTAS DO RETORNO (USAR SOMENTE EM TESTE)

                  $arrTeste = array(
                  'id' => $nossoNumero,
                  'codigobarras' => $nossoNumero,
                  'id_cliente' => 3,
                  'id_agendamento' => 1,
                  'id_conta' => 6,
                  'tecnico_responsavel' => 190,
                  'valor' => $valorRecebido,
                  'status' => 'RECEBER',
                  'vencimento' => date('Y-m-d'),
                  );


                  $dup = $finModel->ret($nossoNumero);
                  if (count($dup) == 0) {
                  $salvar = ContainerController::insertAdvanced('contas_receber', $arrTeste);
                  }else{
                  $dp = $dp + 1;
                  }
                  //$deteAll = ContainerController::delete('contas_receber', array('id' => $nossoNumero));
                  //$deteAll = ContainerController::delete('f_contas_pagar_receber', array('id_pagamento' => $nossoNumero));

                 */
                $json_seis = json_encode($arr);
                /* ### Aqui ele salva um JSON com os dados de retorno na tabela retorno */
                $salvar = ContainerController::updateAdvanced("retorno", array("liquidacao" => $json_seis), array("id" => $idRetorno));
                $dup = $finModel->ret($nossoNumero);
                if (count($dadosConta) > 1) {
                    $duplicatas = $duplicatas + 1;
                }
                $liquidacao = $liquidacao + 1;
                $montante = $montante + $valorRecebido;
                $tf = $tf + $tarifa;

                /* ### Aqui ele da um update na nossa tabela para Recebido */
                $update = ContainerController::updateAdvanced("contas_receber", array("status" => 'recebido'), array('id' => $nossoNumero));


                // PARTE PARA ADICIONAR AO BANCO DO FABIO
                $dateCont = $finModel->dadosdeConta($nossoNumero);

                $dados = array(
                    'id_pagamento' => $dateCont[0]->id,
                    'tipo' => 'RECEBER',
                    'id_categoria' => $bank[1]->id,
                    'id_centrocusto' => $bank[2]->id,
                    'id_cliente' => $dateCont[0]->id_cliente,
                    'cliente_tipo' => "cliente",
                    'id_contabancaria' => $idConta,
                    'descricao' => $dateCont[0]->obs,
                    'data_vencimento' => $dateCont[0]->vencimento,
                    'situacao' => "PAGO",
                    'valor' => $arr[$nossoNumero]['valor'],
                    'id_tecnico' => $dateCont[0]->tecnico_responsavel,
                    'id_agendamento' => $dateCont[0]->id_agendamento,
                    'id_suporte' => 0,
                    'num_doc' => $nossoNumero,
                    'id_pagamento' => $nossoNumero,
                    'valor_pago' => $arr[$nossoNumero]['valor'],
                );

//                if ($finModel->verficaIdPagamento($dateCont[0]->id) == 0) {
//                    $finContRec->adicionar($dados);
//                }

                if ($finModel->verficaIdPagamento($dateCont[0]->id) == 0) {
                    $finContRec->adicionar($dados);
                }

                /* if ($finModel->verficaIdPagamento($dateCont[0]->id) == 0) {
                  $finContRec->adicionar($dados);
                  } */
            } elseif ($cod == 9) {
                // Registro

                $arrNove[$nossoNumero]['nDocumento'] = $t_num_doc_cob;
                $arrNove[$nossoNumero]['nome'] = $row_receber->nome;
                $arrNove[$nossoNumero]['tarifa'] = $tarifa;
                $arrNove[$nossoNumero]['multa'] = $multa;
                $arrNove[$nossoNumero]['numero'] = $nossoNumero;

                //echo "CLIENTE: $row_receber->nome<br>VALOR: $liquido<br>TARIFA: $tarifa <br>NUMERO: " . $nossoNumero . "<br> CODIGO: $cod | $codNome <br>BAIXA: " . $baixa . "<br>REJEITADA: " . $rejeitada . "</br>DATA CREDITO:$dataCredito<br><br>#############################################<br>";
                $nosso1 = ContainerController::selectWhere("contas_receber", "*", array("id" => $nossoNumero));

                $date = date_create($dataCredito);
                $arrNovo = array(
                    "nosso1" => $nosso1[0]->nosso1 + 1,
                    "baixa" => 1,
                    "baixa_tarifa" => $tarifa,
                    "baixa_data" => date_format($date, "Y-m-d"),
                );
                $update = ContainerController::updateAdvanced("contas_receber", $arrNovo, array("id" => $nossoNumero));
                die;
                $mt = $mt + $tarifa;
                $registradas = $registradas + 1;
                $json_nove = json_encode($arrNove);
                /* ### Aqui ele salva um JSON com os dados de retorno na tabela retorno */
                $salvar = ContainerController::updateAdvanced("retorno", array("registro" => $json_nove), array("id" => $idRetorno));
                $baixas = $baixas + 1;
            } elseif ($cod == 2) {
                $dados = array(
                    "nNossoNumero" => $nossoNumero,
                    "multa" => $multa,
                    "tarifa" => $tarifa,
                    "valor" => $liquido
                );
                dd($dados);
            }
        }
        $vlBruto2 = $montante - $tf;
        $vlLiquido = $vlBruto2 - $mt;



//        echo "Multa:$mt <br>"; //Valor a pagar de multa !
//        echo "Duplicatas: $duplicatas <br>";
//        echo "Registradas: $registradas <br>";
//        echo "Liquidadas: $liquidacao <br>";
//        echo "SPC $spc <br>";
//        echo "Tarifas: $tf <br>";
//        echo "Valor Bruto 1: $montante <br>";
//        echo "Valor Bruto 2: $vlBruto2 <br>";
//        echo "Valor Liquido: $vlLiquido<br>";
// Tarifa
        $this->view([
            'title' => 'Retorno',
            'nArquivo' => $nome,
            'duplicatas' => $duplicatas,
            'baixas' => $registradas,
            'liquidadas' => $liquidacao,
            'tarifa' => $tf,
            'idRetorno' => $idRetorno,
            'perda' => $vlPagar,
            'spc' => $spc,
            'arrSpc' => $arrSpc,
            'liquido' => $vlBruto2,
            'dados' => $date[0],
            'totalLiquido' => $vlLiquido,
            'arrNegociado' => $arrNegociados,
            'negociado' => $negociados,
            'cadPor' => $cadastradoPor->cname,
            'montante' => $montante], 'financeiro.retornoresult'
        );
    }

    public function retorno($request) {
        erros();

        $output_dir = "/usr/local/nexus-mvc/public/uploads"; //Path for file upload
        $name = $_FILES["arquivo"]['name'];
        $fileCount = count($_FILES["arquivo"]['name']);
        $RandomNum = time();
        $ImageName = str_replace(' ', '-', strtolower($_FILES['arquivo']['name']));
        $ImageType = $_FILES['arquivo']['type']; //"image/png", image/jpeg etc.

        $tmp = $_FILES['arquivo']['tmp_name'];
        $ImageExt = substr($ImageName, strrpos($ImageName, '.'));
        $ImageExt = str_replace('.', '', $ImageExt);
        $ImageName = preg_replace("/\.[^.\s]{3,4}$/", "", $ImageName);
        $NewImageName = $ImageName . '-' . $RandomNum . '.' . $ImageExt;
        $ret[$NewImageName] = $output_dir . $NewImageName;
        $data = array(
            'image' => $NewImageName
        );
        $select = ContainerController::executeSql("SELECT * FROM `remessa` WHERE `remessa` = '$name'");
        echo count($select) . "<br>";
        if (count($select) == 0) {
            $existe = 1;
            //echo "Cadastrando...";
            $conteudo = file_get_contents($tmp);
            $post = array("retorno" => $name, "conteudo" => $conteudo);
            $add = ContainerController::insertAdvanced('remessa', $post);
            echo "$add";
            move_uploaded_file($_FILES["arquivo"]["tmp_name"], $output_dir . '/' . $NewImageName);
            $this->notifications("success", "Remessa", "Remessa cadastrado com sucesso.");
        } else {

            //echo "Já existe  | $name | $fileCount";
            $existe = 0;
            $conteudo = file_get_contents($tmp);
            $select = ContainerController::executeSql("SELECT * FROM `retorno` WHERE `retorno` = '$name'");

            $this->notifications("info", "Remessa", "Essa remessa já foi cadastrado, vamos listar o que há dentro dele!");
        }
        $this->view(
                [
            'title' => 'Teste',
            'nArquivo' => $name,
            'conteudo' => $conteudo], 'financeiro.retorno'
        );
    }

    public function printRetorno($request) {
        erros();

        //Instancias 
        $finModel = new Financeiro();

        // Pega o Id do retorno no Request
        $idRetorno = $request->parameter;
        $row_receber = $finModel->dadosRetorno($idRetorno); //Busca os dados do retorno
        $nomeArq = $row_receber->retorno; //Armazena o nome do retorno 

        $pasta = $_SERVER['DOCUMENT_ROOT'] . "/uploads"; // Onde Fica salvo os retornos

        $dados = file_get_contents($pasta . "/" . $nomeArq); //Lê os dados do retorno


        /* DADOS DE CONTAGEM */

        $vlPagar = 0; //Total de contas Baixadas
        $baixas = 0; //Quantidade de contas baixadas

        $duplicatas = 0; // Contas Duplicadas

        $liq = 0; // Quantidade de contas liquidadas
        $montante = 0; // Total das contas liquidadas
        $tarifa = 0; // Tarifas das contas liquidadas


        $liquidacao = $row_receber->liquidacao;
        $liquidacao = json_decode($liquidacao);

        $arrSpc = array();
        $spc = 0;

        //var_dump($liquidacao["valor"]);
        foreach ($liquidacao as $value) {
//            var_dump($value);
//            echo "<br><hr><br>";
            $tarifa = $tarifa + $value->tarifa;
            $montante = $montante + $value->valor;
            $liq = $liq + 1;
            $dup = $finModel->retorno_duplicata($value->nDocumento);
            if ($dup == true) {
                $duplicatas = $duplicatas + 1;
            }
            if ($value->spc == true) {
                $arrSpc[$value->nDocumento]["valor"] = $value->valor;
                $arrSpc[$value->nDocumento]["nDocumento"] = $value->nDocumento;
                $arrSpc[$value->nDocumento]["nome"] = $value->nome;
                $spc = $spc + 1;
            }
        }
        $mLiquido = $montante - $tarifa;


        $registro = $row_receber->registro;
        $registro = json_decode($registro);
        foreach ($registro as $value) {
//            var_dump($value);
//            echo "<br><hr><br>";
            $baixas = $baixas + 1;
            $vlPagar = $vlPagar + $value->tarifa;
        }
//        echo "Tarifa = $tarifa";;
//        echo "<br> Montante: $montante";
//        echo "<br> C. Liquidadas: $liq";
//        echo "<br> Duplicatas: $duplicatas";
//        
//        echo "<br> Registradas: $baixas";
//        echo "<br> Multa de Registro: $vlPagar";
//        
//        echo "<br> Montante Final: $mFinal";
//        
//        echo "<br><hr><br>";
        $mFinal = $mLiquido - $vlPagar; // Valor total recebido 
        $this->view([
            'title' => 'Print Retorno ',
            'nArquivo' => $nomeArq,
            'baixadas' => $baixas,
            'valorBaixadas' => $vlPagar,
            'duplicadas' => $duplicatas,
            'liquidadas' => $liq,
            'montante' => $montante,
            'tarifa' => $tarifa,
            'spc' => $spc,
            'mFinal' => $mFinal,
            'mLiquido' => $mLiquido,
            'arrSpc' => $arrSpc,
                ], 'financeiro.printRetorno'
        );
    }

    /*
     * CLIENTES SEM COBRANÇA 
     *       */

    public function clientes_sem_cobranca($request) {
        $this->view(['title' => 'Clientes sem Cobrança'], 'financeiro.clientSCob');
    }

    public function to_datatable_clientesSCob($request) {

        $tabela = New Financeiro();
        $tabela->to_datatable_clientesSCob();
    }

}
