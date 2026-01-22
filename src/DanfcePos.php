<?php

namespace Alves\POS;

use Alves\Escpos\EscposImage;
use Alves\Escpos\PrintConnectors\PrintConnector;
use Alves\Escpos\Printer;

class DanfcePos
{
    /**
     * NFCe
     * @var \SimpleXMLElement
     */
    protected $nfce;

    /**
     * protNFe
     * @var \SimpleXMLElement
     */
    protected $protNFe;

    /**
     * Printer
     * @var Printer
     */
    protected $printer;

    /**
     * Logo do emitente
     * @var EscposImage
     */
    protected $logo;

    /**
     * Total de itens da NFCe
     * @var integer
     */
    protected $totItens = 0;

    /**
     * URI referente a pagina de consulta da NFCe pela chave de acesso
     * @var string
     */
    protected $uri = '';

    /** CONFIGURAÇÕES */
    protected $colunas          = 48;
    protected $segundaVia       = false;

    /**
     * Modelo de impressão 
     * 1 - Normal
     * 2 - Compacto
     * @var string
     */
    protected $modeloImpressao  = 1;

    /** LARGURAS DINÂMICAS */
    protected $colSeq;
    protected $colCod;
    protected $colDesc;
    protected $colQtd;
    protected $colUn;
    protected $colVUn;
    protected $colVTot;

    /**
     * Carrega o conector da impressora.
     * @param Printer $printer
     * @param string $xml
     * @param string $logo
     * @param int $colunas
     * @param boolean $contingencia
     * @param boolean $segundaVia
     * @param int $modeloImpressao
     */
    public function __construct(PrintConnector $connector, $xml, $logopath = '', $colunas = 48, $segundaVia = false, $modeloImpressao = 1)
    {
        $this->printer              = new Printer($connector);
        $this->colunas              = $colunas;
        $this->segundaVia           = $segundaVia;
        $this->modeloImpressao      = $modeloImpressao;

        $this->loadNFCe($xml);

        if ($logopath != '') {
            $this->logo($logopath);
        }
    }

    /**
     * Carrega a NFCe
     * @param string $nfcexml
     */
    public function loadNFCe($nfcexml)
    {
        $xml            = $nfcexml;

        if (is_file($nfcexml)) {
            $xml        = @file_get_contents($nfcexml);
        }

        if (empty($xml)) {
            throw new \InvalidArgumentException('Não foi possível ler o documento.');
        }

        $nfe            = simplexml_load_string($xml, null, LIBXML_NOCDATA);
        $this->protNFe  = $nfe->protNFe;
        $this->nfce     = $nfe->NFe;

        if (empty($this->protNFe)) {
            $this->nfce = $nfe;
        }
    }

    /**
     * Carrega o logo do emitente
     * @param string $logopath
     */
    public function logo($logopath)
    {
        $this->logo = EscposImage::load($logopath);
    }


    /**
     * =========================
     * IMPRESSÃO PRINCIPAL - Imprime o DANFCE
     * =========================
     */
    public function imprimir()
    {
        $this->printer->setFont(Printer::FONT_B);

        $this->parteI();

        $this->printer->setLineSpacing(20);


        $this->parteII();
        $this->parteIII();
        $this->parteIV();
        $this->parteV();
        $this->parteVI();
        $this->parteVII();
        $this->parteVIII();
        $this->parteIX();

        $this->printer->feed(2);
        $this->printer->cut();
        $this->printer->close();
    }

    /**
     * Parte I - Emitente
     * Dados do emitente
     * Campo Obrigatório
     */
    protected function parteI()
    {
        $emit  = $this->nfce->infNFe->emit;
        $ender = $emit->enderEmit;

        $razao    = trim((string) $emit->xNome);
        $fantasia = trim((string) $emit->xFant);
        $cnpj     = (string) $emit->CNPJ;
        $ie       = trim((string) $emit->IE);

        $log    = trim((string) $ender->xLgr);
        $nro    = trim((string) $ender->nro);
        $bairro = trim((string) $ender->xBairro);
        $mun    = trim((string) $ender->xMun);
        $uf     = trim((string) $ender->UF);
        $cep    = trim((string) ($ender->CEP ?? ''));
        $compl  = trim((string) ($ender->xCpl ?? ''));
        $fone   = trim((string) ($ender->fone ?? ''));

        $this->uri = (string) $this->nfce->infNFeSupl->urlChave;

        /** Cabeçalho centralizado */
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        /** LOGO */
        if (!empty($this->logo)) {
            try {
                $this->printer->bitImage($this->logo);
            } catch (\Throwable $th) {
                // Não interrompe impressão
            }
        }

        /** NOME FANTASIA */
        if (!empty($fantasia)) {
            $this->printer->setEmphasis(true);
            $this->printer->text($fantasia . "\n");
            $this->printer->setEmphasis(false);
        }

        /** RAZÃO SOCIAL */
        if (!empty($razao)) {
            $this->printer->text($razao . "\n");
        }

        /** CNPJ / IE */
        $linhaDoc = 'CNPJ: ' . $this->formatarCNPJ($cnpj);
        if (!empty($ie)) {
            $linhaDoc .= '  IE: ' . $ie;
        }
        $this->printer->text($linhaDoc . "\n");

        /** ENDEREÇO */
        $linhaEndereco = $log . ', ' . $nro;
        if (!empty($compl)) {
            $linhaEndereco .= ' - ' . $compl;
        }
        $this->printer->text($linhaEndereco . "\n");

        /** BAIRRO / CIDADE / UF / CEP */
        $linhaCidade = $bairro . ' - ' . $mun . '/' . $uf;
        if (!empty($cep)) {
            $linhaCidade .= ' - CEP: ' . $this->formatarCEP($cep);
        }
        $this->printer->text($linhaCidade);

        /** TELEFONE */
        if (!empty($fone)) {
            $this->printer->text(
                '- Fone: ' . $this->formatarFone($fone) . "\n"
            );
        }

        // $this->printer->feed();

        /** Volta alinhamento para o restante da NFC-e */
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
    }

    /**
     * Parte II - Identificação do DANFE NFC-e
     * Campo Obrigatório
     */
    protected function parteII()
    {
        // Centralização via firmware (mesma lógica da Parte I)
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        $titulo = 'DOCUMENTO AUXILIAR DA NOTA FISCAL DE CONSUMIDOR ELETRÔNICA';

        // $this->printer->setEmphasis(true);
        $this->printer->text($titulo . "\n" . "\n");
        // $this->printer->setEmphasis(false);

        // $this->separador();

        // IMPORTANTE: volta para LEFT para o corpo da NFC-e
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
    }

    /**
     * Parte III - Detalhes da Venda
     */
    protected function parteIII()
    {
        // $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        // $this->printer->setEmphasis(true);
        // $this->printer->text("ITENS DA VENDA\n");
        // $this->printer->setEmphasis(false);
        // $this->separador();

        // Cabeçalho das colunas
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        $this->printer->setEmphasis(true);
        $this->printer->text(
            "#    Cód          Descrição    Qtd    Un         Valor    Total\n\n"
        );
        $this->printer->setEmphasis(false);

        $det = $this->nfce->infNFe->det;
        $this->totItens = $det->count();

        for ($x = 0; $x < $this->totItens; $x++) {

            $seq    = $x + 1;
            $cProd  = (string) $det[$x]->prod->cProd;
            $xProd  = (string) $det[$x]->prod->xProd;
            $qCom   = (float)  $det[$x]->prod->qCom;
            $uCom   = (string) $det[$x]->prod->uCom;
            $vUnCom = (float)  $det[$x]->prod->vUnCom;
            $vProd  = (float)  $det[$x]->prod->vProd;

            // Valores auxiliares do item
            $vFreteItem = (float) ($det[$x]->prod->vFrete ?? 0);
            $vDescItem  = (float) ($det[$x]->prod->vDesc ?? 0);

            // Valor líquido do item
            $vLiquido = $vProd + $vFreteItem - $vDescItem;

            $obsItem = (string) ($det[$x]->infAdProd ?? '');

            // Linha 1 – Seq + Código + Descrição
            $seqItem = $this->strPad($seq, 3, '0', STR_PAD_LEFT);

            // Linha 1 – Seq (3) + Código + Descrição (linha cheia)
            $linha1 = $seqItem . '  ' . $this->strPad($cProd, 6, '0', STR_PAD_LEFT) . ' ' . $this->strPad($xProd, 48);

            $this->printer->text($linha1 . "\n" . "\n");

            // Linha 2 – Quantidade x Valor Unitário         Total
            $linha2 =
                $this->strPad('', 30) .
                $this->strPad(number_format($qCom, 2, ',', '.'), 6, ' ', STR_PAD_LEFT) .
                '  ' . $this->strPad($uCom, 3) .
                '   x  ' .
                $this->strPad(number_format($vUnCom, 2, ',', '.'), 6, ' ', STR_PAD_LEFT) .
                $this->strPad(number_format($vProd, 2, ',', '.'), 10, ' ', STR_PAD_LEFT);

            $this->printer->text($linha2 . "\n" . "\n");

            // Observações
            if (!empty($obsItem)) {
                $this->printObservacaoItem($obsItem);
            }

            // Auxiliares
            if ($vFreteItem > 0) {
                $this->printValorAuxItem('+ Frete', $vFreteItem);
            }

            if ($vDescItem > 0) {
                $this->printValorAuxItem('- Desconto', $vDescItem);
            }

            if ($vFreteItem > 0 || $vDescItem > 0) {
                $this->printValorAuxItem('= Valor Líquido', $vLiquido);
            }
        }


        // Separador
        // $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        // $this->separador();

        // =========================
        // TOTALIZADORES
        // =========================
        // $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        $this->printer->setEmphasis(true);

        $this->printTotalLinhaFiscal(
            'QTD. TOTAL DE ITENS',
            $this->strPad($this->totItens, 3, ' ', STR_PAD_LEFT),
            '',
            0,
            2,
            1
        );

        $this->printTotalLinhaFiscal(
            'VALOR TOTAL R$',
            (float) $this->nfce->infNFe->total->ICMSTot->vProd,
            '+'
        );

        if (($this->nfce->infNFe->total->ICMSTot->vFrete ?? 0) > 0) {
            $this->printTotalLinhaFiscal(
                'Frete R$',
                (float) ($this->nfce->infNFe->total->ICMSTot->vFrete ?? 0),
                '+'
            );
        }

        if ($this->nfce->infNFe->total->ICMSTot->vDesc > 0) {
            $this->printTotalLinhaFiscal(
                'Desconto R$',
                (float) $this->nfce->infNFe->total->ICMSTot->vDesc,
                '-'
            );
        }

        // $this->separador();

        $this->printTotalLinhaFiscal(
            'VALOR A PAGAR R$',
            (float) $this->nfce->infNFe->total->ICMSTot->vNF,
            ''
        );
        $this->printer->setEmphasis(false);
        $this->printer->setTextSize(1, 1);
        $this->printer->setEmphasis(false);
    }

    /**
     * Parte IV - Totais da Venda
     * Campo Obrigatório
     */

    protected function colunasPagamento()
    {
        $colDesc  = (int) ($this->colunas * 0.60);
        $colValor = $this->colunas - $colDesc;

        return [$colDesc, $colValor];
    }

    protected function headerFormasPagamento()
    {
        $linha =
            $this->strPad('FORMA DE PAGAMENTO', 40, ' ', STR_PAD_RIGHT) .
            $this->strPad('VALOR PAGO', 22, ' ', STR_PAD_LEFT);

        $this->printer->setEmphasis(true);
        $this->printer->text($linha . "\n" . "\n");
        $this->printer->setEmphasis(false);
    }

    protected function parteIV()
    {
        $pag = $this->nfce->infNFe->pag->detPag;

        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        $this->separador();

        $this->printer->setJustification(Printer::JUSTIFY_LEFT);

        // Cabeçalho obrigatório
        $this->headerFormasPagamento();

        foreach ($pag as $pagI) {

            $descricao = $this->tipoPag((string) $pagI->tPag);
            $valor     = (float) $pagI->vPag;

            // Bandeira do cartão
            if (isset($pagI->card->tBand)) {
                $bandeira = $this->bandeiraCartao($pagI->card->tBand);
                if ($bandeira !== '') {
                    $descricao .= ' ' . $bandeira . '';
                }
            }

            $this->printFormaPagamento($descricao, $valor);
        }

        // Troco (não é forma de pagamento)
        if (
            isset($this->nfce->infNFe->pag->vTroco) &&
            (float) $this->nfce->infNFe->pag->vTroco > 0
        ) {
            $this->printer->setEmphasis(true);
            $this->printFormaPagamento(
                'TROCO R$',
                (float) $this->nfce->infNFe->pag->vTroco
            );
            $this->printer->setEmphasis(false);
        }
    }


    /**
     * Parte V - Mensagem Fiscal e Informações da Consulta via Chave de Acesso
     * (Divisão VI do Manual NFC-e)
     * Campo Obrigatório
     */
    protected function parteV()
    {
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        // Ambiente
        $tpAmb = (int) $this->nfce->infNFe->ide->tpAmb;
        if ($tpAmb === 2) {
            $this->printer->setEmphasis(true);
            $this->printer->text(
                "EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO - SEM VALOR FISCAL\n\n"
            );
            $this->printer->setEmphasis(false);
        }

        // Contingência
        $tpEmis = (int) $this->nfce->infNFe->ide->tpEmis;
        if ($tpEmis !== 1) {
            $this->printer->setEmphasis(true);
            $this->printer->text("EMITIDA EM CONTINGÊNCIA\n\n");
            $this->printer->setEmphasis(false);
        }

        // $this->printer->feed(1);

        $this->separador();
    }

    /**
     * Parte VI - Informações do Consumidor
     * Campo Opcional
     */
    protected function parteVI()
    {
        $Id = (string) $this->nfce->infNFe->attributes()->{'Id'};
        $chave = substr($Id, 3); // remove "NFe"

        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        // Consulta
        $this->printer->text("Consulte pela chave de acesso em\n\n");
        $this->printer->text($this->uri . "\n" . "\n");

        // Chave de acesso mascarada (11 blocos de 4)
        $this->printer->text(
            $this->mascaraChaveAcesso($chave) . "\n" . "\n"
        );

        /**
         * Mensagens fiscais da SEFAZ (xMsg)
         */
        if (
            isset($this->protNFe->infProt->xMsg) &&
            trim((string) $this->protNFe->infProt->xMsg) !== ''
        ) {
            $this->printer->setEmphasis(true);
            $this->printer->text("INFORMAÇÕES ADICIONAIS\n\n");
            $this->printer->setEmphasis(false);

            $this->printer->text(
                trim((string) $this->protNFe->infProt->xMsg) . "\n" . "\n"
            );
            $this->printer->feed(1);
        }

        /**
         * Dados do consumidor
         */
        if (!isset($this->nfce->infNFe->dest)) {
            $this->printer->setEmphasis(true);
            $this->printer->text("CONSUMIDOR NÃO IDENTIFICADO\n\n");
            $this->printer->setEmphasis(false);
            return;
        }

        $dest = $this->nfce->infNFe->dest;

        // Documentos
        if (!empty($dest->CNPJ)) {
            $this->printer->text("CONSUMIDOR CNPJ: " . $this->formatarCNPJ($dest->CNPJ) . "\n" . "\n");
        }

        if (!empty($dest->CPF)) {
            $this->printer->text("CONSUMIDOR CPF: " . $this->formatarCPF($dest->CPF) . "\n" . "\n");
        }

        if (!empty($dest->idEstrangeiro)) {
            $this->printer->text("CONSUMIDOR ID. ESTRANGEIRO: " . $dest->idEstrangeiro . "\n" . "\n");
        }

        // Nome
        if (!empty($dest->xNome)) {
            $this->printer->text((string) $dest->xNome . "\n" . "\n");
        }

        /**
         * Endereço (opcional)
         */
        if (isset($dest->enderDest)) {

            $end = $dest->enderDest;

            $linha1 = trim(
                (string) $end->xLgr . ', ' . (string) $end->nro
            );

            if ($linha1 !== ',') {
                $this->printer->text($linha1 . "\n" . "\n");
            }

            $linha2 = '';

            if (!empty($end->xCpl)) {
                $linha2 .= (string) $end->xCpl . ', ';
            }

            if (!empty($end->xBairro)) {
                $linha2 .= (string) $end->xBairro . '. ';
            }

            if (!empty($end->xMun) && !empty($end->UF)) {
                $linha2 .= (string) $end->xMun . ' - ' . (string) $end->UF;
            }

            if ($linha2 !== '') {
                $this->printer->text($linha2 . "\n" . "\n");
            }
        }
    }

    /**
     * Parte VII - QRCode
     * Campo Obrigatório
     */
    protected function parteVII()
    {
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        $nNF   = preg_replace('/[^0-9]/', '', (string) $this->nfce->infNFe->ide->nNF);
        $serie = preg_replace('/[^0-9]/', '', (string) $this->nfce->infNFe->ide->serie);
        $dhEmi = (string) $this->nfce->infNFe->ide->dhEmi;

        $linha =
            'NFC-e nº ' . str_pad($nNF, 9, '0', STR_PAD_LEFT) .
            ' | Série ' . str_pad($serie, 3, '0', STR_PAD_LEFT) .
            ' | ' . date('d/m/Y H:i:s', strtotime($dhEmi));

        $this->printer->setEmphasis(true);
        $this->printer->text($linha . "\n" . "\n");
        $this->printer->setEmphasis(false);

        /**
         * Protocolo de autorização
         */
        if (
            isset($this->protNFe) &&
            isset($this->protNFe->infProt)
        ) {
            $nProt    = (string) $this->protNFe->infProt->nProt;
            $dhRecbto = (string) $this->protNFe->infProt->dhRecbto;

            if ($nProt !== '') {
                $this->printer->text(
                    "Protocolo de autorização: " . $nProt . "\n" . "\n"
                );
            }

            if ($dhRecbto !== '') {
                $this->printer->text(
                    "Data de autorização: " .
                        date('d/m/Y H:i:s', strtotime($dhRecbto)) . "\n" . "\n"
                );
            }
        } else {
            $this->printer->setEmphasis(true);
            $this->printer->text(
                "NOTA FISCAL INVÁLIDA - SEM PROTOCOLO\n" . "\n"
            );
            $this->printer->setEmphasis(false);
        }

        $this->printer->feed(1);

        /**
         * QRCode NFC-e
         */
        if (
            isset($this->nfce->infNFeSupl) &&
            !empty($this->nfce->infNFeSupl->qrCode)
        ) {
            $qr = trim((string) $this->nfce->infNFeSupl->qrCode);

            if ($qr !== '') {
                $this->printer->setJustification(Printer::JUSTIFY_CENTER);
                $this->printer->qrCode(
                    $qr,
                    Printer::QR_ECLEVEL_L,
                    6,
                    Printer::QR_MODEL_2
                );
                $this->printer->feed(1);
            }
        }
    }


    /**
     * Parte VIII - Informação de tributos
     * Campo Obrigatório
     */
    protected function parteVIII()
    {
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        $vTotTrib = 0.00;

        if (
            isset($this->nfce->infNFe->total->ICMSTot->vTotTrib)
        ) {
            $vTotTrib = (float) $this->nfce->infNFe->total->ICMSTot->vTotTrib;
        }

        // Linha principal
        $linha = 'Informação dos Tributos: R$ ' . number_format($vTotTrib, 2, ',', '.');

        $this->printer->text($linha . "\n" . "\n");

        // Fonte legal
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->text(
            'Fonte IBPT - Lei Federal 12.741/2012' . "\n" . "\n"
        );

        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        // $this->separador();
    }


    /**
     * Parte IX - Mensagem de Interesse do Contribuinte
     * Conteúdo de infCpl
     * Campo Opcional
     */
    protected function parteIX()
    {
        $infCpl = '';

        if (
            isset($this->nfce->infNFe->infAdic) &&
            isset($this->nfce->infNFe->infAdic->infCpl)
        ) {
            $infCpl = (string) $this->nfce->infNFe->infAdic->infCpl;
        }

        if (!empty($infCpl)) {

            // Normaliza separadores comuns
            $infCpl = str_replace(
                array(';', '|'),
                "\n\n",
                $infCpl
            );

            $this->printer->setJustification(Printer::JUSTIFY_CENTER);

            // Quebra o texto conforme largura configurada
            $linhas = wordwrap(
                $infCpl,
                $this->colunas,
                "\n",
                true
            );

            $this->printer->text($linhas . "\n" . "\n");
        }

        $this->separador();

        // Assinatura / branding
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->text(
            "GCWeb - Em suas mãos, um mundo de possibilidades\n"
        );
    }


    /**
     * =========================
     * FUNÇÕES BASE
     * =========================
     */

    protected function formatarCEP($cep)
    {
        $cep = preg_replace('/[^0-9]/', '', $cep);

        if (strlen($cep) === 8) {
            return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        }

        return $cep;
    }

    function formatarFone(string $fone): string
    {
        // Remove tudo que não for número
        $fone = preg_replace('/\D/', '', $fone);

        // Telefone fixo (10 dígitos)
        if (strlen($fone) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($fone, 0, 2),
                substr($fone, 2, 4),
                substr($fone, 6, 4)
            );
        }

        // Celular (11 dígitos)
        if (strlen($fone) === 11) {
            return sprintf(
                '(%s) %s-%s',
                substr($fone, 0, 2),
                substr($fone, 2, 5),
                substr($fone, 7, 4)
            );
        }

        // Caso inválido
        return '';
    }

    function formatarCNPJ(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return '';
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }

    /**
     * Formata CPF para o padrão 000.000.000-00
     *
     * @param string $cpf
     * @return string
     */
    protected function formatarCPF($cpf)
    {
        // Remove qualquer caractere que não seja número
        $cpf = preg_replace('/\D/', '', $cpf);

        // Garante 11 dígitos
        if (strlen($cpf) !== 11) {
            return $cpf; // retorna sem formatar se inválido
        }

        return substr($cpf, 0, 3) . '.' .
            substr($cpf, 3, 3) . '.' .
            substr($cpf, 6, 3) . '-' .
            substr($cpf, 9, 2);
    }

    protected function strPad($text, $length, $char = ' ', $type = STR_PAD_RIGHT)
    {
        return str_pad(mb_substr($text, 0, $length), $length, $char, $type);
    }

    protected function centerText(string $texto, int $colunas = 48)
    {
        $texto = trim($texto);
        $tamanho = mb_strlen($texto, 'UTF-8');

        if ($tamanho >= $colunas) {
            return $texto;
        }

        $espacos = intdiv($colunas - $tamanho, 2);

        return str_repeat(' ', $espacos) . $texto;
    }

    protected function wrapTexto(string $texto, int $colunas = 48)
    {
        return explode("\n", wordwrap($texto, $colunas, "\n", true));
    }

    protected function separador($char = '-')
    {
        $this->printer->text(str_repeat($char, $this->colunas) . "\n");
    }

    /**
     * Function to encode a number as two bytes. This is straight out of Alves\Escpos\Printer
     * @param int $tPag
     * @return string
     */
    protected function intLowHigh($input, $length)
    {
        $outp = "";
        for ($i = 0; $i < $length; $i++) {
            $outp .= chr($input % 256);
            $input = (int) ($input / 256);
        }
        return $outp;
    }

    /**
     * Returns payment method as text.
     * @param int $tPag
     * @return string
     */
    protected function tipoPag($tPag)
    {
        $aPag = [
            '01' => 'Dinheiro',
            '02' => 'Cheque',
            '03' => 'Cartão de Crédito',
            '04' => 'Cartão de Débito',
            '05' => 'Cartão da Loja (Private Label)',
            '10' => 'Vale Alimentação',
            '11' => 'Vale Refeição',
            '12' => 'Vale Presente',
            '13' => 'Vale Combustível',
            '14' => 'Duplicata Mercantil',
            '15' => 'Boleto Bancário',
            '16' => 'Depósito Bancario',
            '17' => 'PIX Dinâmico',
            '18' => 'Transferência Carteira Digital',
            '19' => 'Prog.Fidel., CashBack, Créd.Virt.',
            '20' => 'PIX Estático',
            '21' => 'Crédito em loja',
            '22' => 'Pag.Eletr. Não Informado (Falha de hardware)',
            '90' => 'Sem Pagamento',
            '99' => 'Outros'
        ];

        if (array_key_exists($tPag, $aPag)) {
            return $aPag[$tPag];
        }
        return '';
    }

    protected function bandeiraCartao($tBand)
    {
        switch ((string) $tBand) {
            case '01':
                return 'Visa';
            case '02':
                return 'MasterCard';
            case '03':
                return 'Amex';
            case '04':
                return 'Sorocred';
            case '05':
                return 'Diners';
            case '06':
                return 'Elo';
            case '07':
                return 'HiperCard';
            case '08':
                return 'Aura';
            case '09':
                return 'Cabal';
            case '10':
                return 'Alelo';
            case '11':
                return 'Banes';
            case '12':
                return 'CalCard';
            case '13':
                return 'Credz';
            case '14':
                return 'Discover';
            case '15':
                return 'GoodCard';
            case '16':
                return 'GreenCard';
            case '17':
                return 'Hiper';
            case '18':
                return 'Jcb';
            case '19':
                return 'Mais';
            case '20':
                return 'Maxvan';
            case '21':
                return 'PoliCard';
            case '22':
                return 'RedeCompras';
            case '23':
                return 'Sodexo';
            case '24':
                return 'Valecard';
            case '25':
                return 'VeroCheque';
            case '26':
                return 'Vr';
            case '27':
                return 'Ticket';
            default:
                return '';
        }
    }


    protected function printFormaPagamento(string $descricao, float $valor)
    {

        $linha =
            $this->strPad($descricao, 42, ' ', STR_PAD_RIGHT) .
            $this->strPad(
                'R$ ' . number_format($valor, 2, ',', '.'),
                20,
                ' ',
                STR_PAD_LEFT
            );

        $this->printer->text($linha . "\n" . "\n");
    }

    protected function printValorAuxItem(string $label, float $valor)
    {
        $linha =
            $this->strPad($label, 28, ' ', STR_PAD_RIGHT) .
            $this->strPad(
                number_format($valor, 2, ',', '.'),
                20,
                ' ',
                STR_PAD_LEFT
            );

        $this->printer->text($linha . "\n" . "\n");
    }

    protected function printTotalLinhaFiscal(string $label, float $valor, string $operador = '+', int $casasDecimais = 2, int $fValue = 0)
    {

        if ($fValue == 0) {
            $valorFormatado = ($operador != '' ? ($operador === '-' ? '- ' : '+ ') : '')
                . number_format(abs($valor), $casasDecimais, ',', '.');
        } else {
            $valorFormatado = str_pad($valor, 3, '0', STR_PAD_LEFT);
        }

        $linha =
            str_pad($label, 42, ' ', STR_PAD_RIGHT) .
            str_pad($valorFormatado, 20, ' ', STR_PAD_LEFT);

        $this->printer->text($linha . "\n" . "\n");
    }

    protected function printObservacaoItem(string $texto, int $largura = 48)
    {
        $texto = trim(str_replace(["\r", "\n"], ' ', $texto));
        $linhas = str_split($texto, $largura);
        $count = 0;

        foreach ($linhas as $linha) {
            $str = '';

            if ($count == 0) {
                $str = '   Obs: ';
            }

            $this->printer->text($str . $linha . "\n" . "\n");

            $count++;
        }
    }

    /**
     * Formata a chave de acesso da NFC-e conforme o Manual
     * 44 dígitos em 11 blocos de 4 números
     *
     * Exemplo:
     * 12345678901234567890123456789012345678901234
     * =>
     * 1234 5678 9012 3456 7890 1234 5678 9012 3456 7890 1234
     *
     * @param string $chave
     * @return string
     */
    protected function mascaraChaveAcesso(string $chave)
    {
        return trim(implode(' ', str_split($chave, 4)));
    }
}
