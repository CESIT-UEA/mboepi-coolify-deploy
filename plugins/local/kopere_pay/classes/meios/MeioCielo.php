<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * MeioCielo.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

// https://developercielo.github.io/manual/cielo-ecommerce#c%C3%B3digos-da-api
// https://github.com/DeveloperCielo/API-3.0-PHP

use Cielo\API30\Ecommerce\CieloEcommerce;
use Cielo\API30\Ecommerce\CreditCard;
use Cielo\API30\Ecommerce\Payment;
use Cielo\API30\Ecommerce\Request\CieloRequestException;
use Cielo\API30\Ecommerce\Sale;
use Cielo\API30\Environment;
use Cielo\API30\Merchant;
use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_select;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\util\button_util;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\util\send_event;
use local_kopere_pay\util\timeend_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_enrollment;
use local_kopere_pay\vo\local_kopere_pay_history;

/**
 * Class MeioCielo
 */
class MeioCielo implements IMeio {

    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        return [
            'name' => 'Cielo',
            'public_name' => 'Cartão de Crédito',
            'class' => 'MeioCielo',
            'enable' => self::is_enable(),
            'mensalidade' => self::is_mensal(),
            'escolha' => true,
        ];
    }

    /**
     * Function is_enable
     *
     * @return bool
     */
    public static function is_enable() {
        if (config::get_key("kopere_pay-habilitar-MeioCielo")) {
            return true;
        }

        return false;
    }

    /**
     * Function is_mensal
     *
     * @return false
     */
    public static function is_mensal() {
        return false;
    }

    /**
     * @param form $form
     * @param \stdClass $koperepaydetalhe
     */
    public static function details_edit_form(Form $form, $koperepaydetalhe) {
    }

    /**
     * @param form $form
     *
     * @return void
     * @throws \Exception
     */
    public static function edit(form $form) {
        $form->add_input(
            input_text::new_instance()
                ->set_title('MERCHANT ID')
                ->set_value_by_config('kopere_pay-meiocielo-merchantid')
                ->set_description(
                    "'MERCHANT ID' e 'MERCHANT KEY' é enviado por e-mail na ativação da API.
                     Mais detalhes em <a href='https://minhaconta2.cielo.com.br/minha-conta/webservice3' target='_blank'>
                     https://minhaconta2.cielo.com.br/minha-conta/webservice3</a>"
                )
                ->set_required()
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title('MERCHANT KEY')
                ->set_value_by_config('kopere_pay-meiocielo-merchantkey')
                ->set_required()
        );

        $parcelas = [
            ["key" => 0, "value" => "Somente a vista"],
        ];
        for ($i = 1; $i <= 12; $i++) {
            $parcelas[] = ["key" => $i, "value" => "{$i} x"];
        }
        $form->add_input(
            input_select::new_instance()
                ->set_title('Máximo de Parcelas')
                ->set_values($parcelas)
                ->set_value_by_config('kopere_pay-meiocielo-numparcelas')
                ->set_description("Especifique a quantidade máxima de parcelas que o aluno pode escolher!")
                ->set_required()
        );

        if (config::get_key('kopere_pay-meiocielo-minparcela') < 5) {
            set_config('kopere_pay-meiocielo-minparcela', 5, "local_kopere_dashboard");
        }

        $form->add_input(
            input_text::new_instance()
                ->set_title('Valor mínimo de cada parcela')
                ->set_value_by_config('kopere_pay-meiocielo-minparcela')
                ->set_description("Especifique o preço mínimo de cada parcela!")
                ->set_required()
        );
    }

    /**
     * @param local_kopere_pay_detail $detalhe
     * @param \stdClass $course
     *
     * @return string
     * @throws \Exception
     */
    public static function pay_button($detalhe, $course) {
        global $CFG;

        if ($detalhe->charge != 'mensalidade') {
            $link = "{$CFG->wwwroot}/local/kopere_pay/r.php?id={$detalhe->course}&meio=MeioCielo&enroll=1";
            return button_util::link($detalhe, self::get_name(), $link);
        }

        return '';
    }

    /**
     * Function returned
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function returned() {
        global $DB, $USER, $CFG;

        $enroll = optional_param('enroll', false, PARAM_INT);
        $courseid = optional_param('id', false, PARAM_INT);

        if ($enroll) {

            $course = $DB->get_record('course', ['id' => $courseid]);

            /** @var local_kopere_pay_detail $detalhe */
            $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $course->id]);
            $coupon = coupon_util::get_cupom($course->id);

            $price = coupon_util::get_preco($detalhe, $coupon);

            $enrollmentid = local_kopere_pay_enrollment::add_enrollment($USER->id, $course->id, 'MeioCielo', $coupon, $price);
            local_kopere_pay_history::add_history($enrollmentid, [], []);

            header::location(
                "{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}&completed=1&meio=MeioCielo&enrollment={$enrollmentid}"
            );
        }
    }

    /**
     * @param     $course
     * @param     $user
     * @param int $local_kopere_pay_rename_key
     * @throws \Exception
     */
    public static function completed($course, $user) {
        global $DB, $OUTPUT, $CFG;

        require_once(__DIR__ . '/cielo/vendor/autoload.php');

        $local_kopere_pay_rename_key = optional_param('enrollment', 0, PARAM_INT);
        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $local_kopere_pay_rename_key]);

        $cardNumber = optional_param('card-number', false, PARAM_TEXT);
        $cardName = optional_param('card-name', false, PARAM_TEXT);
        $cardMes = optional_param('card-mes', false, PARAM_TEXT);
        $cardAno = optional_param('card-ano', false, PARAM_TEXT);
        $cardCvv = optional_param('card-cvv', false, PARAM_TEXT);
        $cardParcelas = optional_param('card-parcelas', false, PARAM_TEXT);

        $returned = ['status' => false];
        if ($cardNumber) {
            $returned = self::completed_pagar($local_kopere_pay_rename_key, $enrollment);
        }

        $optionsMes = $optionsAno = "";
        for ($i = 1; $i <= 12; $i++) {
            $i = substr("0{$i}", -2);
            if ($cardMes == $i) {
                $optionsMes .= "<option selected value='{$i}'>{$i}</option>";
            } else {
                $optionsMes .= "<option value='{$i}'>{$i}</option>";
            }
        }
        for ($i = date('Y'); $i <= date('Y') + 12; $i++) {
            if ($cardAno == $i) {
                $optionsAno .= "<option selected value='{$i}'>{$i}</option>";
            } else {
                $optionsAno .= "<option value='{$i}'>{$i}</option>";
            }
        }

        $statusText = "";
        if (isset($returned['returnCode'])) {
            $status = self::getInternalStatus($returned['returnCode']);

            if ($returned['status'] === false) {
                $status['DEFINICAO'];
                $status['ACAO'];

                $statusText = message::danger(
                    "<b>" . $status['DEFINICAO'] . "</b><br>" .
                    $status['ACAO']
                );
            }
        }

        if ($returned['status'] === true) {
            header::location("{$CFG->wwwroot}/course/view.php?id={$enrollment->course}");
        }
        if ($returned['status'] === false) {

            $numParcelas = config::get_key('kopere_pay-meiocielo-numparcelas');
            $minParcela = config::get_key('kopere_pay-meiocielo-minparcela');

            $testParcelas = intval($enrollment->value) / $minParcela;
            if ($testParcelas < $numParcelas) {
                $numParcelas = (int) $testParcelas;
            }

            if ($numParcelas > 1) {

                $options = '';
                for ($i = 2; $i <= $numParcelas; $i++) {
                    if ($cardParcelas == $i) {
                        $options .= "<option selected value='{$i}'>{$i} parcelas</option>";
                    } else {
                        $options .= "<option value='{$i}'>{$i} parcelas</option>";
                    }
                }
                $parcelamento = '
                    <div class="form-group">
                        <label>Parcelamento:</label>
                        <select name="card-parcelas">
                            <option value="1">A vista</option>
                            ' . $options . '
                        </select>
                    </div>';
            } else {
                $parcelamento = "";
            }

            echo "<div class='text-center'>";
            echo $OUTPUT->heading("Matrícula concluída, aguardando confirmação de pagamento.", 2);
            echo $OUTPUT->heading("Número da transação: <strong style='color:#EF5350'>{$local_kopere_pay_rename_key}</strong>", 3);
            echo "</div>";

            echo "
            <article class='card' style='max-width:500px;margin:0 auto;padding:12px;'>
                <div class='card-body p-5'>
                    {$statusText}
                    <form role='form' method='post'>
                        <div class='form-group'>
                            <label for='card-number'>* Número do cartão:</label>
                            <input type='text' class='form-control' name='card-number' required
                                   placeholder='Digite o número do cartão' style='width:100%;'
                                   value='{$cardNumber}'>
                        </div>

                        <div class='form-group'>
                            <label for='card-name'>* Nome impresso no cartão:</label>
                            <input type='text' class='form-control' name='card-name' required
                                   placeholder='Como impresso no cartão' style='width:100%;'
                                   value='{$cardName}'>
                        </div>

                        <div class='form-group'>
                            <label>* Validade</label>
                            <div class='input-group' style='display:flex;align-items:center;flex-wrap:nowrap;'>
                                <select type='number' class='form-control' name='card-mes' required>
                                <option>Selecione o Mês</option>
                                    {$optionsMes}
                                </select>
                                <span style='width:35px;text-align:center;font-size:30px;'>/</span>
                                <select type='number' class='form-control' name='card-ano' required>
                                <option>Selecione o Ano</option>
                                    {$optionsAno}
                                </select>
                            </div>
                        </div>

                        <div class='form-group'>
                            <label>* Cód. Segurança(CVV):</label>
                            <input type='number' class='form-control' name='card-cvv' required
                                   style='width:80px' value='{$cardCvv}'>
                        </div>

                        {$parcelamento}

                        <input type='submit' class='subscribe btn btn-primary btn-block' value='Pagar'>
                    </form>
                </div>
            </article>";
        }
    }

    /**
     * @param $course
     * @return mixed
     */
    public static function enroll($course) {
    }

    /**
     * @param $local_kopere_pay_rename_key
     * @return array
     * @throws \Exception
     */
    protected static function completed_pagar($local_kopere_pay_rename_key, $enrollment) {
        global $USER, $DB;

        $cardNumber = optional_param('card-number', false, PARAM_TEXT);
        $cardName = optional_param('card-name', false, PARAM_TEXT);
        $cardMes = optional_param('card-mes', false, PARAM_TEXT);
        $cardAno = optional_param('card-ano', false, PARAM_TEXT);
        $cardCvv = optional_param('card-cvv', false, PARAM_TEXT);
        $cardParcelas = optional_param('card-parcelas', false, PARAM_TEXT);
        $cardParcelas = max(1, min(12, $cardParcelas));

        // Configure o ambiente
        $environment = Environment::production();

        // Configure seu merchant
        $merchant = new Merchant(
            config::get_key('kopere_pay-meiocielo-merchantid'),
            config::get_key('kopere_pay-meiocielo-merchantkey')
        );

        // Crie uma instância de Sale informando o ID do pedido na loja
        $sale = new Sale("{$local_kopere_pay_rename_key}");

        // Crie uma instância de Customer informando o nome do cliente
        $sale->customer(fullname($USER));

        // Crie uma instância de Payment informando o valor do pagamento
        $payment = $sale->payment(
            self::cielo_amount($enrollment->value),
            $cardParcelas
        );

        // Crie uma instância de Credit Card utilizando os dados de teste
        // esses dados estão disponíveis no manual de integração
        $payment->setType(Payment::PAYMENTTYPE_CREDITCARD)
            ->creditCard("{$cardCvv}", self::getbrand($cardNumber))
            ->setExpirationDate("{$cardMes}/{$cardAno}")
            ->setCardNumber("{$cardNumber}")
            ->setHolder("{$cardName}");

        // Crie o pagamento na Cielo
        try {
            // Configure o SDK com seu merchant e o ambiente apropriado para criar a venda
            $sale = (new CieloEcommerce($merchant, $environment))->createSale($sale);

            // Com o ID do pagamento, podemos fazer sua captura, se ela não tiver sido capturada ainda
            (new CieloEcommerce($merchant, $environment))->captureSale(
                $sale->getPayment()->getPaymentId(),
                self::cielo_amount($enrollment->value),
                0
            );

            $receive = [
                "paymentId" => $sale->getPayment()->getPaymentId(),
                "tid" => $sale->getPayment()->getTid(),
                "returnCode" => $sale->getPayment()->getReturnCode(),
                "returnMessage" => $sale->getPayment()->getReturnMessage(),
            ];

            if ($sale->getPayment()->getReturnCode() == 0 || $sale->getPayment()->getReturnCode() == 11) {
                $receive['status'] = true;
                local_kopere_pay_history::add_history($local_kopere_pay_rename_key, [], json_encode($receive));

                // adiciona matrícula
                $course = $DB->get_record('course', ['id' => $enrollment->course]);
                $koperepaydetalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $enrollment->course]);
                $timeend = timeend_util::calculate_day($koperepaydetalhe);
                enroll_util::enrol($course, $USER, time(), $timeend);

                enrollment_util::changue_status($enrollment, enrollment_util::PAID);
                send_event::kopere_pay_pago($course, $USER);

                return $receive;
            } else {
                $receive['status'] = false;
                local_kopere_pay_history::add_history($local_kopere_pay_rename_key, [], json_encode($receive),
                    $sale->getPayment()->getReturnMessage()
                );
                return $receive;
            }

        } catch (CieloRequestException $e) {
            // Em caso de erros de integração, podemos tratar o erro aqui.
            // os códigos de erro estão todos disponíveis no manual de integração.

            $receive = [
                'returnCode' => "-1",
                'returnMessage' => $e->getCieloError()->getMessage(),
                'status' => false,
            ];
            local_kopere_pay_history::add_history($local_kopere_pay_rename_key, [], [], $e->getCieloError()->getMessage());

            return $receive;
        }
    }

    /**
     * Function getbrand
     *
     * @param $card
     * @return int|string
     */
    protected static function getbrand($card) {
        $brands = [
            CreditCard::VISA => '/^4\d{12}(\d{3})?$/',
            CreditCard::MASTERCARD => '/^(5[1-5]\d{4}|677189)\d{10}$/',
            CreditCard::DINERS => '/^3(0[0-5]|[68]\d)\d{11}$/',
            CreditCard::DISCOVER => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
            CreditCard::ELO => '/^((((636368)|(438935)|(504175)|(451416)|(636297))\d{0,10})|((5067)|(4576)|(4011))\d{0,12})$/',
            CreditCard::AMEX => '/^3[47]\d{13}$/',
            CreditCard::JCB => '/^(?:2131|1800|35\d{3})\d{11}$/',
            CreditCard::AURA => '/^(5078\d{2})(\d{2})(\d{11})$/',
            CreditCard::HIPERCARD => '/^(606282\d{10}(\d{3})?)|(3841\d{15})$/',
            // CreditCard::MAESTRO => '/^(?:5[0678]\d\d|6304|6390|67\d\d)\d{8,15}$/',
        ];
        // Run test
        foreach ($brands as $brand => $regex) {
            if (preg_match($regex, $card)) {
                return $brand;
            }
        }

        return "";
    }

    /**
     * @param local_kopere_pay_history $historico
     *
     * @return string
     * @throws \Exception
     */
    public static function get_status($historico) {
        $receive = json_decode($historico->receive, true);

        if (isset($receive['modo'])) {
            return MeioManual::get_status($receive);
        }

        if (!isset($receive['returnCode'])) {
            return "";
        }
        $status = self::getInternalStatus($receive["returnCode"]);

        $link =
            "https://admin.braspag.com.br/ReportPagador/TransactionDetails/{$receive['paymentId']}?history=False&printVersion=False";

        if (isset($status['DEFINICAO'])) {
            return "<strong>{$status['DEFINICAO']}</strong><br>{$status['ACAO']}<br>
                <strong>Transação ID:</strong> <a href='{$link}' target='_blank'>{$receive['paymentId']}</a>
                <strong>TID:</strong> {$receive['tid']}<br>";
        }
        return '';
    }

    /**
     * Function getInternalStatus
     *
     * @param $returnCode
     * @return array|mixed
     */
    protected static function getInternalStatus($returnCode) {
        $status = [
            -1 => [
                "DEFINICAO" => "Erro na API",
                "SIGNIFICADO" => "A API retornou um erro",
                "ACAO" => "Erro na API",
            ],
            0 => [
                "DEFINICAO" => "Transação autorizada com sucesso.",
                "SIGNIFICADO" => "Transação autorizada com sucesso.",
                "ACAO" => "Transação autorizada com sucesso.",
            ],
            1 => [
                "DEFINICAO" => "Transação não autorizada. Transação referida.",
                "SIGNIFICADO" => "Transação não autorizada. Referida (suspeita de fraude) pelo banco emissor.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            2 => [
                "DEFINICAO" => "Transação não autorizada. Transação referida.",
                "SIGNIFICADO" => "Transação não autorizada. Referida (suspeita de fraude) pelo banco emissor.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            3 => [
                "DEFINICAO" => "Transação não permitida. Erro no cadastramento do código do estabelecimento no arquivo de configuração do TEF",
                "SIGNIFICADO" => "Transação não permitida. Estabelecimento inválido. Entre com contato com a Cielo.",
                "ACAO" => "Não foi possível processar a transação. Entre com contato com a Loja Virtual.",
            ],
            4 => [
                "DEFINICAO" => "Transação não autorizada. Cartão bloqueado pelo banco emissor.",
                "SIGNIFICADO" => "Transação não autorizada. Cartão bloqueado pelo banco emissor.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            5 => [
                "DEFINICAO" => "Transação não autorizada. Cartão inadimplente (Do not honor).",
                "SIGNIFICADO" => "Transação não autorizada. Não foi possível processar a transação. Questão relacionada a segurança, inadimplencia ou limite do portador.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            6 => [
                "DEFINICAO" => "Transação não autorizada. Cartão cancelado.",
                "SIGNIFICADO" => "Transação não autorizada. Não foi possível processar a transação. Cartão cancelado permanentemente pelo banco emissor.",
                "ACAO" => "Não foi possível processar a transação. Entre em contato com seu banco emissor.",
            ],
            7 => [
                "DEFINICAO" => "Transação negada. Reter cartão condição especial",
                "SIGNIFICADO" => "Transação não autorizada por regras do banco emissor.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor",
            ],
            8 => [
                "DEFINICAO" => "Transação não autorizada. Código de segurança inválido.",
                "SIGNIFICADO" => "Transação não autorizada. Código de segurança inválido. Oriente o portador a corrigir os dados e tentar novamente.",
                "ACAO" => "Transação não autorizada. Dados incorretos. Reveja os dados e informe novamente.",
            ],
            9 => [
                "DEFINICAO" => "Transação cancelada parcialmente com sucesso.",
                "SIGNIFICADO" => "Transação cancelada parcialmente com sucesso",
                "ACAO" => "Transação cancelada parcialmente com sucesso",
            ],
            11 => [
                "DEFINICAO" => "Transação autorizada com sucesso para cartão emitido no exterior",
                "SIGNIFICADO" => "Transação autorizada com sucesso.",
                "ACAO" => "Transação autorizada com sucesso.",
            ],
            12 => [
                "DEFINICAO" => "Transação inválida, erro no cartão.",
                "SIGNIFICADO" => "Não foi possível processar a transação. Solicite ao portador que verifique os dados do cartão e tente novamente.",
                "ACAO" => "Não foi possível processar a transação. reveja os dados informados e tente novamente. Se o erro persistir, entre em contato com seu banco emissor.",
            ],
            13 => [
                "DEFINICAO" => "Transação não permitida. Valor da transação Inválido.",
                "SIGNIFICADO" => "Transação não permitida. Valor inválido. Solicite ao portador que reveja os dados e novamente. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Transação não autorizada. Valor inválido. Refazer a transação confirmando os dados informados. Persistindo o erro, entrar em contato com a loja virtual.",
            ],
            14 => [
                "DEFINICAO" => "Transação não autorizada. Cartão Inválido",
                "SIGNIFICADO" => "Transação não autorizada. Cartão inválido. Pode ser bloqueio do cartão no banco emissor, dados incorretos ou tentativas de testes de cartão. Use o Algoritmo de Lhum (Mod 10) para evitar transações não autorizadas por esse motivo. Consulte www.cielo.com.br/desenvolvedores para implantar o Algoritmo de Lhum.",
                "ACAO" => "Não foi possível processar a transação. reveja os dados informados e tente novamente. Se o erro persistir, entre em contato com seu banco emissor.",
            ],
            15 => [
                "DEFINICAO" => "Banco emissor indisponível ou inexistente.",
                "SIGNIFICADO" => "Transação não autorizada. Banco emissor indisponível.",
                "ACAO" => "Não foi possível processar a transação. Entre em contato com seu banco emissor.",
            ],
            19 => [
                "DEFINICAO" => "Refaça a transação ou tente novamente mais tarde.",
                "SIGNIFICADO" => "Não foi possível processar a transação. Refaça a transação ou tente novamente mais tarde. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Não foi possível processar a transação. Refaça a transação ou tente novamente mais tarde. Se o erro persistir entre em contato com a loja virtual.",
            ],
            21 => [
                "DEFINICAO" => "Cancelamento não efetuado. Transação não localizada.",
                "SIGNIFICADO" => "Não foi possível processar o cancelamento. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Não foi possível processar o cancelamento. Tente novamente mais tarde. Persistindo o erro, entrar em contato com a loja virtual.",
            ],
            22 => [
                "DEFINICAO" => "Parcelamento inválido. Número de parcelas inválidas.",
                "SIGNIFICADO" => "Não foi possível processar a transação. Número de parcelas inválidas. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Não foi possível processar a transação. Valor inválido. Refazer a transação confirmando os dados informados. Persistindo o erro, entrar em contato com a loja virtual.",
            ],
            23 => [
                "DEFINICAO" => "Transação não autorizada. Valor da prestação inválido.",
                "SIGNIFICADO" => "Não foi possível processar a transação. Valor da prestação inválido. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Não foi possível processar a transação. Valor da prestação inválido. Refazer a transação confirmando os dados informados. Persistindo o erro, entrar em contato com a loja virtual.",
            ],
            24 => [
                "DEFINICAO" => "Quantidade de parcelas inválido.",
                "SIGNIFICADO" => "Não foi possível processar a transação. Quantidade de parcelas inválido. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Não foi possível processar a transação. Quantidade de parcelas inválido. Refazer a transação confirmando os dados informados. Persistindo o erro, entrar em contato com a loja virtual.",
            ],
            25 => [
                "DEFINICAO" => "Pedido de autorização não enviou número do cartão",
                "SIGNIFICADO" => "Não foi possível processar a transação. Solicitação de autorização não enviou o número do cartão. Se o erro persistir, verifique a comunicação entre loja virtual e Cielo.",
                "ACAO" => "Não foi possível processar a transação. reveja os dados informados e tente novamente. Persistindo o erro, entrar em contato com a loja virtual.",
            ],
            28 => [
                "DEFINICAO" => "Arquivo temporariamente indisponível.",
                "SIGNIFICADO" => "Não foi possível processar a transação. Arquivo temporariamente indisponível. Reveja a comunicação entre Loja Virtual e Cielo. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Não foi possível processar a transação. Entre com contato com a Loja Virtual.",
            ],
            30 => [
                "DEFINICAO" => "Transação não autorizada. Decline Message",
                "SIGNIFICADO" => "Não foi possível processar a transação. Solicite ao portador que reveja os dados e tente novamente. Se o erro persistir verifique a comunicação com a Cielo esta sendo feita corretamente",
                "ACAO" => "Não foi possível processar a transação. Reveja os dados e tente novamente. Se o erro persistir, entre em contato com a loja",
            ],
            39 => [
                "DEFINICAO" => "Transação não autorizada. Erro no banco emissor.",
                "SIGNIFICADO" => "Transação não autorizada. Erro no banco emissor.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            41 => [
                "DEFINICAO" => "Transação não autorizada. Cartão bloqueado por perda.",
                "SIGNIFICADO" => "Transação não autorizada. Cartão bloqueado por perda.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            43 => [
                "DEFINICAO" => "Transação não autorizada. Cartão bloqueado por roubo.",
                "SIGNIFICADO" => "Transação não autorizada. Cartão bloqueado por roubo.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            51 => [
                "DEFINICAO" => "Transação não autorizada. Limite excedido/sem saldo.",
                "SIGNIFICADO" => "Transação não autorizada. Limite excedido/sem saldo.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            52 => [
                "DEFINICAO" => "Cartão com dígito de controle inválido.",
                "SIGNIFICADO" => "Não foi possível processar a transação. Cartão com dígito de controle inválido.",
                "ACAO" => "Transação não autorizada. Reveja os dados informados e tente novamente.",
            ],
            53 => [
                "DEFINICAO" => "Transação não permitida. Cartão poupança inválido",
                "SIGNIFICADO" => "Transação não permitida. Cartão poupança inválido.",
                "ACAO" => "Não foi possível processar a transação. Entre em contato com seu banco emissor.",
            ],
            54 => [
                "DEFINICAO" => "Transação não autorizada. Cartão vencido",
                "SIGNIFICADO" => "Transação não autorizada. Cartão vencido.",
                "ACAO" => "Transação não autorizada. Refazer a transação confirmando os dados.",
            ],
            55 => [
                "DEFINICAO" => "Transação não autorizada. Senha inválida",
                "SIGNIFICADO" => "Transação não autorizada. Senha inválida.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            57 => [
                "DEFINICAO" => "Transação não permitida para o cartão",
                "SIGNIFICADO" => "Transação não autorizada. Transação não permitida para o cartão.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            58 => [
                "DEFINICAO" => "Transação não permitida. Opção de pagamento inválida.",
                "SIGNIFICADO" => "Transação não permitida. Opção de pagamento inválida. Reveja se a opção de pagamento escolhida está habilitada no cadastro",
                "ACAO" => "Transação não autorizada. Entre em contato com sua loja virtual.",
            ],
            59 => [
                "DEFINICAO" => "Transação não autorizada. Suspeita de fraude.",
                "SIGNIFICADO" => "Transação não autorizada. Suspeita de fraude.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            60 => [
                "DEFINICAO" => "Transação não autorizada.",
                "SIGNIFICADO" => "Transação não autorizada. Tente novamente. Se o erro persistir o portador deve entrar em contato com o banco emissor.",
                "ACAO" => "Não foi possível processar a transação. Tente novamente mais tarde. Se o erro persistir, entre em contato com seu banco emissor.",
            ],
            61 => [
                "DEFINICAO" => "Banco emissor indisponível.",
                "SIGNIFICADO" => "Transação não autorizada. Banco emissor indisponível.",
                "ACAO" => "Transação não autorizada. Tente novamente. Se o erro persistir, entre em contato com seu banco emissor.",
            ],
            62 => [
                "DEFINICAO" => "Transação não autorizada. Cartão restrito para uso doméstico",
                "SIGNIFICADO" => "Transação não autorizada. Cartão restrito para uso doméstico.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            63 => [
                "DEFINICAO" => "Transação não autorizada. Violação de segurança",
                "SIGNIFICADO" => "Transação não autorizada. Violação de segurança.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            64 => [
                "DEFINICAO" => "Transação não autorizada. Valor abaixo do mínimo exigido pelo banco emissor.",
                "SIGNIFICADO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
                "ACAO" => "Transação não autorizada. Valor abaixo do mínimo exigido pelo banco emissor.",
            ],
            65 => [
                "DEFINICAO" => "Transação não autorizada. Excedida a quantidade de transações para o cartão.",
                "SIGNIFICADO" => "Transação não autorizada. Excedida a quantidade de transações para o cartão.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            67 => [
                "DEFINICAO" => "Transação não autorizada. Cartão bloqueado para compras hoje.",
                "SIGNIFICADO" => "Transação não autorizada. Cartão bloqueado para compras hoje. Bloqueio pode ter ocorrido por excesso de tentativas inválidas. O cartão será desbloqueado automaticamente à meia noite.",
                "ACAO" => "Transação não autorizada. Cartão bloqueado temporariamente. Entre em contato com seu banco emissor.",
            ],
            70 => [
                "DEFINICAO" => "Transação não autorizada. Limite excedido/sem saldo.",
                "SIGNIFICADO" => "Transação não autorizada. Limite excedido/sem saldo.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            72 => [
                "DEFINICAO" => "Cancelamento não efetuado. Saldo disponível para cancelamento insuficiente.",
                "SIGNIFICADO" => "Cancelamento não efetuado. Saldo disponível para cancelamento insuficiente. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Cancelamento não efetuado. Tente novamente mais tarde. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            74 => [
                "DEFINICAO" => "Transação não autorizada. A senha está vencida.",
                "SIGNIFICADO" => "Transação não autorizada. A senha está vencida.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            75 => [
                "DEFINICAO" => "Senha bloqueada. Excedeu tentativas de cartão.",
                "SIGNIFICADO" => "Transação não autorizada.",
                "ACAO" => "Sua Transação não pode ser processada. Entre em contato com o Emissor do seu cartão.",
            ],
            76 => [
                "DEFINICAO" => "Cancelamento não efetuado. Banco emissor não localizou a transação original",
                "SIGNIFICADO" => "Cancelamento não efetuado. Banco emissor não localizou a transação original",
                "ACAO" => "Cancelamento não efetuado. Entre em contato com a loja virtual.",
            ],
            77 => [
                "DEFINICAO" => "Cancelamento não efetuado. Não foi localizado a transação original",
                "SIGNIFICADO" => "Cancelamento não efetuado. Não foi localizado a transação original",
                "ACAO" => "Cancelamento não efetuado. Entre em contato com a loja virtual.",
            ],
            78 => [
                "DEFINICAO" => "Transação não autorizada. Cartão bloqueado primeiro uso.",
                "SIGNIFICADO" => "Transação não autorizada. Cartão bloqueado primeiro uso. Solicite ao portador que desbloqueie o cartão diretamente com seu banco emissor.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor e solicite o desbloqueio do cartão.",
            ],
            80 => [
                "DEFINICAO" => "Transação não autorizada. Divergencia na data de transação/pagamento.",
                "SIGNIFICADO" => "Transação não autorizada. Data da transação ou data do primeiro pagamento inválida.",
                "ACAO" => "Transação não autorizada. Refazer a transação confirmando os dados.",
            ],
            82 => [
                "DEFINICAO" => "Transação não autorizada. Cartão inválido.",
                "SIGNIFICADO" => "Transação não autorizada. Cartão Inválido. Solicite ao portador que reveja os dados e tente novamente.",
                "ACAO" => "Transação não autorizada. Refazer a transação confirmando os dados. Se o erro persistir, entre em contato com seu banco emissor.",
            ],
            83 => [
                "DEFINICAO" => "Transação não autorizada. Erro no controle de senhas",
                "SIGNIFICADO" => "Transação não autorizada. Erro no controle de senhas",
                "ACAO" => "Transação não autorizada. Refazer a transação confirmando os dados. Se o erro persistir, entre em contato com seu banco emissor.",
            ],
            85 => [
                "DEFINICAO" => "Transação não permitida. Falha da operação.",
                "SIGNIFICADO" => "Transação não permitida. Houve um erro no processamento.Solicite ao portador que digite novamente os dados do cartão, se o erro persistir pode haver um problema no terminal do lojista, nesse caso o lojista deve entrar em contato com a Cielo.",
                "ACAO" => "Transação não permitida. Informe os dados do cartão novamente. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            86 => [
                "DEFINICAO" => "Transação não permitida. Falha da operação.",
                "SIGNIFICADO" => "Transação não permitida. Houve um erro no processamento.Solicite ao portador que digite novamente os dados do cartão, se o erro persistir pode haver um problema no terminal do lojista, nesse caso o lojista deve entrar em contato com a Cielo.",
                "ACAO" => "Transação não permitida. Informe os dados do cartão novamente. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            88 => [
                "DEFINICAO" => "Falha na criptografia dos dados.",
                "SIGNIFICADO" => "Falha na criptografia dos dados.",
                "ACAO" => "Entre em contato com seu banco emissor.",
            ],
            89 => [
                "DEFINICAO" => "Erro na transação.",
                "SIGNIFICADO" => "Transação não autorizada. Erro na transação. O portador deve tentar novamente e se o erro persistir, entrar em contato com o banco emissor.",
                "ACAO" => "Transação não autorizada. Erro na transação. Tente novamente e se o erro persistir, entre em contato com seu banco emissor.",
            ],
            90 => [
                "DEFINICAO" => "Transação não permitida. Falha da operação.",
                "SIGNIFICADO" => "Transação não permitida. Houve um erro no processamento.Solicite ao portador que digite novamente os dados do cartão, se o erro persistir pode haver um problema no terminal do lojista, nesse caso o lojista deve entrar em contato com a Cielo.",
                "ACAO" => "Transação não permitida. Informe os dados do cartão novamente. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            91 => [
                "DEFINICAO" => "Transação não autorizada. Banco emissor temporariamente indisponível.",
                "SIGNIFICADO" => "Transação não autorizada. Banco emissor temporariamente indisponível.",
                "ACAO" => "Transação não autorizada. Banco emissor temporariamente indisponível. Entre em contato com seu banco emissor.",
            ],
            92 => [
                "DEFINICAO" => "Transação não autorizada. Tempo de comunicação excedido.",
                "SIGNIFICADO" => "Transação não autorizada. Tempo de comunicação excedido.",
                "ACAO" => "Transação não autorizada. Comunicação temporariamente indisponível. Entre em contato com a loja virtual.",
            ],
            93 => [
                "DEFINICAO" => "Transação não autorizada. Violação de regra - Possível erro no cadastro.",
                "SIGNIFICADO" => "Transação não autorizada. Violação de regra - Possível erro no cadastro.",
                "ACAO" => "Sua transação não pode ser processada. Entre em contato com a loja virtual.",
            ],
            94 => [
                "DEFINICAO" => "Transação duplicada.",
                "SIGNIFICADO" => "Transação duplicada enviado para autorização/captura.",
                "ACAO" => "O estabelecimento deve revisar as transações enviadas.",
            ],
            96 => [
                "DEFINICAO" => "Falha no processamento.",
                "SIGNIFICADO" => "Não foi possível processar a transação. Falha no sistema da Cielo. Se o erro persistir, entre em contato com a Cielo.",
                "ACAO" => "Sua Transação não pode ser processada, Tente novamente mais tarde. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            97 => [
                "DEFINICAO" => "Valor não permitido para essa transação.",
                "SIGNIFICADO" => "Transação não autorizada. Valor não permitido para essa transação.",
                "ACAO" => "Transação não autorizada. Valor não permitido para essa transação.",
            ],
            98 => [
                "DEFINICAO" => "Sistema/comunicação indisponível.",
                "SIGNIFICADO" => "Transação não autorizada. Sistema do emissor sem comunicação. Se for geral, verificar SITEF, GATEWAY e/ou Conectividade.",
                "ACAO" => "Sua Transação não pode ser processada, Tente novamente mais tarde. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            99 => [
                "DEFINICAO" => "Sistema/comunicação indisponível.",
                "SIGNIFICADO" => "Transação não autorizada. Sistema do emissor sem comunicação. Tente mais tarde. Pode ser erro no SITEF, favor verificar !",
                "ACAO" => "Sua Transação não pode ser processada, Tente novamente mais tarde. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            475 => [
                "DEFINICAO" => "Timeout de Cancelamento",
                "SIGNIFICADO" => "A aplicação não respondeu dentro do tempo esperado.",
                "ACAO" => "Realizar uma nova tentativa após alguns segundos. Persistindo, entrar em contato com o Suporte.",
            ],
            999 => [
                "DEFINICAO" => "Sistema/comunicação indisponível.",
                "SIGNIFICADO" => "Transação não autorizada. Sistema do emissor sem comunicação. Tente mais tarde. Pode ser erro no SITEF, favor verificar !",
                "ACAO" => "Sua Transação não pode ser processada, Tente novamente mais tarde. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            "AA" => [
                "DEFINICAO" => "Tempo Excedido",
                "SIGNIFICADO" => "Tempo excedido na comunicação com o banco emissor. Oriente o portador a tentar novamente, se o erro persistir será necessário que o portador contate seu banco emissor.",
                "ACAO" => "Tempo excedido na sua comunicação com o banco emissor, tente novamente mais tarde. Se o erro persistir, entre em contato com seu banco.",
            ],
            "AC" => [
                "DEFINICAO" => "Transação não permitida. Cartão de débito sendo usado com crédito. Use a função débito.",
                "SIGNIFICADO" => "Transação não permitida. Cartão de débito sendo usado com crédito. Solicite ao portador que selecione a opção de pagamento Cartão de Débito.",
                "ACAO" => "Transação não autorizada. Tente novamente selecionando a opção de pagamento cartão de débito.",
            ],
            "AE" => [
                "DEFINICAO" => "Tente Mais Tarde",
                "SIGNIFICADO" => "Tempo excedido na comunicação com o banco emissor. Oriente o portador a tentar novamente, se o erro persistir será necessário que o portador contate seu banco emissor.",
                "ACAO" => "Tempo excedido na sua comunicação com o banco emissor, tente novamente mais tarde. Se o erro persistir, entre em contato com seu banco.",
            ],
            "AF" => [
                "DEFINICAO" => "Transação não permitida. Falha da operação.",
                "SIGNIFICADO" => "Transação não permitida. Houve um erro no processamento.Solicite ao portador que digite novamente os dados do cartão, se o erro persistir pode haver um problema no terminal do lojista, nesse caso o lojista deve entrar em contato com a Cielo.",
                "ACAO" => "Transação não permitida. Informe os dados do cartão novamente. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            "AG" => [
                "DEFINICAO" => "Transação não permitida. Falha da operação.",
                "SIGNIFICADO" => "Transação não permitida. Houve um erro no processamento.Solicite ao portador que digite novamente os dados do cartão, se o erro persistir pode haver um problema no terminal do lojista, nesse caso o lojista deve entrar em contato com a Cielo.",
                "ACAO" => "Transação não permitida. Informe os dados do cartão novamente. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            "AH" => [
                "DEFINICAO" => "Transação não permitida. Cartão de crédito sendo usado com débito. Use a função crédito.",
                "SIGNIFICADO" => "Transação não permitida. Cartão de crédito sendo usado com débito. Solicite ao portador que selecione a opção de pagamento Cartão de Crédito.",
                "ACAO" => "Transação não autorizada. Tente novamente selecionando a opção de pagamento cartão de crédito.",
            ],
            "AI" => [
                "DEFINICAO" => "Transação não autorizada. Autenticação não foi realizada.",
                "SIGNIFICADO" => "Transação não autorizada. Autenticação não foi realizada. O portador não concluiu a autenticação. Solicite ao portador que reveja os dados e tente novamente. Se o erro persistir, entre em contato com a Cielo informando o BIN (6 primeiros dígitos do cartão)",
                "ACAO" => "Transação não autorizada. Autenticação não foi realizada com sucesso. Tente novamente e informe corretamente os dados solicitado. Se o erro persistir, entre em contato com o lojista.",
            ],
            "AJ" => [
                "DEFINICAO" => "Transação não permitida. Transação de crédito ou débito em uma operação que permite apenas Private Label. Tente novamente selecionando a opção Private Label.",
                "SIGNIFICADO" => "Transação não permitida. Transação de crédito ou débito em uma operação que permite apenas Private Label. Solicite ao portador que tente novamente selecionando a opção Private Label. Caso não disponibilize a opção Private Label verifique na Cielo se o seu estabelecimento permite essa operação.",
                "ACAO" => "Transação não permitida. Transação de crédito ou débito em uma operação que permite apenas Private Label. Tente novamente e selecione a opção Private Label. Em caso de um novo erro entre em contato com a loja virtual.",
            ],
            "AV" => [
                "DEFINICAO" => "Transação não autorizada. Dados Inválidos",
                "SIGNIFICADO" => "Falha na validação dos dados da transação. Oriente o portador a rever os dados e tentar novamente.",
                "ACAO" => "Falha na validação dos dados. Reveja os dados informados e tente novamente.",
            ],
            "BD" => [
                "DEFINICAO" => "Transação não permitida. Falha da operação.",
                "SIGNIFICADO" => "Transação não permitida. Houve um erro no processamento.Solicite ao portador que digite novamente os dados do cartão, se o erro persistir pode haver um problema no terminal do lojista, nesse caso o lojista deve entrar em contato com a Cielo.",
                "ACAO" => "Transação não permitida. Informe os dados do cartão novamente. Se o erro persistir, entre em contato com a loja virtual.",
            ],
            "BL" => [
                "DEFINICAO" => "Transação não autorizada. Limite diário excedido.",
                "SIGNIFICADO" => "Transação não autorizada. Limite diário excedido. Solicite ao portador que entre em contato com seu banco emissor.",
                "ACAO" => "Transação não autorizada. Limite diário excedido. Entre em contato com seu banco emissor.",
            ],
            "BM" => [
                "DEFINICAO" => "Transação não autorizada. Cartão Inválido",
                "SIGNIFICADO" => "Transação não autorizada. Cartão inválido. Pode ser bloqueio do cartão no banco emissor ou dados incorretos. Tente usar o Algoritmo de Lhum (Mod 10) para evitar transações não autorizadas por esse motivo.",
                "ACAO" => "Transação não autorizada. Cartão inválido. Refaça a transação confirmando os dados informados.",
            ],
            "BN" => [
                "DEFINICAO" => "Transação não autorizada. Cartão ou conta bloqueado.",
                "SIGNIFICADO" => "Transação não autorizada. O cartão ou a conta do portador está bloqueada. Solicite ao portador que entre em contato com seu banco emissor.",
                "ACAO" => "Transação não autorizada. O cartão ou a conta do portador está bloqueada. Entre em contato com seu banco emissor.",
            ],
            "BO" => [
                "DEFINICAO" => "Transação não permitida. Falha da operação.",
                "SIGNIFICADO" => "Transação não permitida. Houve um erro no processamento. Solicite ao portador que digite novamente os dados do cartão, se o erro persistir, entre em contato com o banco emissor.",
                "ACAO" => "Transação não permitida. Houve um erro no processamento. Digite novamente os dados do cartão, se o erro persistir, entre em contato com o banco emissor.",
            ],
            "BP" => [
                "DEFINICAO" => "Transação não autorizada. Conta corrente inexistente.",
                "SIGNIFICADO" => "Transação não autorizada. Não possível processar a transação por um erro relacionado ao cartão ou conta do portador. Solicite ao portador que entre em contato com o banco emissor.",
                "ACAO" => "Transação não autorizada. Não possível processar a transação por um erro relacionado ao cartão ou conta do portador. Entre em contato com o banco emissor.",
            ],
            "BP176" => [
                "DEFINICAO" => "Transação não permitida.",
                "SIGNIFICADO" => "Parceiro deve checar se o processo de integração foi concluído com sucesso.",
                "ACAO" => "Parceiro deve checar se o processo de integração foi concluído com sucesso.",
            ],
            "BV" => [
                "DEFINICAO" => "Transação não autorizada. Cartão vencido",
                "SIGNIFICADO" => "Transação não autorizada. Cartão vencido.",
                "ACAO" => "Transação não autorizada. Refazer a transação confirmando os dados.",
            ],
            "CF" => [
                "DEFINICAO" => "Transação não autorizada.C79:J79 Falha na validação dos dados.",
                "SIGNIFICADO" => "Transação não autorizada. Falha na validação dos dados. Solicite ao portador que entre em contato com o banco emissor.",
                "ACAO" => "Transação não autorizada. Falha na validação dos dados. Entre em contato com o banco emissor.",
            ],
            "CG" => [
                "DEFINICAO" => "Transação não autorizada. Falha na validação dos dados.",
                "SIGNIFICADO" => "Transação não autorizada. Falha na validação dos dados. Solicite ao portador que entre em contato com o banco emissor.",
                "ACAO" => "Transação não autorizada. Falha na validação dos dados. Entre em contato com o banco emissor.",
            ],
            "DA" => [
                "DEFINICAO" => "Transação não autorizada. Falha na validação dos dados.",
                "SIGNIFICADO" => "Transação não autorizada. Falha na validação dos dados. Solicite ao portador que entre em contato com o banco emissor.",
                "ACAO" => "Transação não autorizada. Falha na validação dos dados. Entre em contato com o banco emissor.",
            ],
            "DF" => [
                "DEFINICAO" => "Transação não permitida. Falha no cartão ou cartão inválido.",
                "SIGNIFICADO" => "Transação não permitida. Falha no cartão ou cartão inválido. Solicite ao portador que digite novamente os dados do cartão, se o erro persistir, entre em contato com o banco",
                "ACAO" => "Transação não permitida. Falha no cartão ou cartão inválido. Digite novamente os dados do cartão, se o erro persistir, entre em contato com o banco",
            ],
            "DM" => [
                "DEFINICAO" => "Transação não autorizada. Limite excedido/sem saldo.",
                "SIGNIFICADO" => "Transação não autorizada. Limite excedido/sem saldo.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            "DQ" => [
                "DEFINICAO" => "Transação não autorizada. Falha na validação dos dados.",
                "SIGNIFICADO" => "Transação não autorizada. Falha na validação dos dados. Solicite ao portador que entre em contato com o banco emissor.",
                "ACAO" => "Transação não autorizada. Falha na validação dos dados. Entre em contato com o banco emissor.",
            ],
            "DS" => [
                "DEFINICAO" => "Transação não permitida para o cartão",
                "SIGNIFICADO" => "Transação não autorizada. Transação não permitida para o cartão.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            "EB" => [
                "DEFINICAO" => "Transação não autorizada. Limite diário excedido.",
                "SIGNIFICADO" => "Transação não autorizada. Limite diário excedido. Solicite ao portador que entre em contato com seu banco emissor.",
                "ACAO" => "Transação não autorizada. Limite diário excedido. Entre em contato com seu banco emissor.",
            ],
            "EE" => [
                "DEFINICAO" => "Transação não permitida. Valor da parcela inferior ao mínimo permitido.",
                "SIGNIFICADO" => "Transação não permitida. Valor da parcela inferior ao mínimo permitido. Não é permitido parcelas inferiores a R$ 5,00. Necessário rever calculo para parcelas.",
                "ACAO" => "Transação não permitida. O valor da parcela está abaixo do mínimo permitido. Entre em contato com a loja virtual.",
            ],
            "EK" => [
                "DEFINICAO" => "Transação não permitida para o cartão",
                "SIGNIFICADO" => "Transação não autorizada. Transação não permitida para o cartão.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            "FA" => [
                "DEFINICAO" => "Transação não autorizada.",
                "SIGNIFICADO" => "Transação não autorizada AmEx.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            "FC" => [
                "DEFINICAO" => "Transação não autorizada. Ligue Emissor",
                "SIGNIFICADO" => "Transação não autorizada. Oriente o portador a entrar em contato com o banco emissor.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            "FD" => [
                "DEFINICAO" => "Transação negada. Reter cartão condição especial",
                "SIGNIFICADO" => "Transação não autorizada por regras do banco emissor.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor",
            ],
            "FE" => [
                "DEFINICAO" => "Transação não autorizada. Divergencia na data de transação/pagamento.",
                "SIGNIFICADO" => "Transação não autorizada. Data da transação ou data do primeiro pagamento inválida.",
                "ACAO" => "Transação não autorizada. Refazer a transação confirmando os dados.",
            ],
            "FF" => [
                "DEFINICAO" => "Cancelamento OK",
                "SIGNIFICADO" => "Transação de cancelamento autorizada com sucesso. ATENÇÂO: Esse retorno é para casos de cancelamentos e não para casos de autorizações.",
                "ACAO" => "Transação de cancelamento autorizada com sucesso",
            ],
            "FG" => [
                "DEFINICAO" => "Transação não autorizada. Ligue AmEx 08007285090.",
                "SIGNIFICADO" => "Transação não autorizada. Oriente o portador a entrar em contato com a Central de Atendimento AmEx.",
                "ACAO" => "Transação não autorizada. Entre em contato com a Central de Atendimento AmEx no telefone 08007285090",
            ],
            "GA" => [
                "DEFINICAO" => "Aguarde Contato",
                "SIGNIFICADO" => "Transação não autorizada. Referida pelo Lynx Online de forma preventiva.",
                "ACAO" => "Transação não autorizada. Entre em contato com o lojista.",
            ],
            "GD" => [
                "DEFINICAO" => "Transação não permitida.",
                "SIGNIFICADO" => "Transação não permitida. Entre em contato com a Cielo.",
                "ACAO" => "Transação não permitida. Entre em contato com a Cielo.",
            ],
            "HJ" => [
                "DEFINICAO" => "Transação não permitida. Código da operação inválido.",
                "SIGNIFICADO" => "Transação não permitida. Código da operação Coban inválido.",
                "ACAO" => "Transação não permitida. Código da operação Coban inválido. Entre em contato com o lojista.",
            ],
            "IA" => [
                "DEFINICAO" => "Transação não permitida. Indicador da operação inválido.",
                "SIGNIFICADO" => "Transação não permitida. Indicador da operação Coban inválido.",
                "ACAO" => "Transação não permitida. Indicador da operação Coban inválido. Entre em contato com o lojista.",
            ],
            "JB" => [
                "DEFINICAO" => "Transação não permitida. Valor da operação inválido.",
                "SIGNIFICADO" => "Transação não permitida. Valor da operação Coban inválido.",
                "ACAO" => "Transação não permitida. Valor da operação Coban inválido. Entre em contato com o lojista.",
            ],
            "KA" => [
                "DEFINICAO" => "Transação não permitida. Falha na validação dos dados.",
                "SIGNIFICADO" => "Transação não permitida. Houve uma falha na validação dos dados. Solicite ao portador que reveja os dados e tente novamente. Se o erro persistir verifique a comunicação entre loja virtual e Cielo.",
                "ACAO" => "Transação não permitida. Houve uma falha na validação dos dados. reveja os dados informados e tente novamente. Se o erro persistir entre em contato com a Loja Virtual.",
            ],
            "KB" => [
                "DEFINICAO" => "Transação não permitida. Selecionado a opção incorrente.",
                "SIGNIFICADO" => "Transação não permitida. Selecionado a opção incorreta. Solicite ao portador que reveja os dados e tente novamente. Se o erro persistir deve ser verificado a comunicação entre loja virtual e Cielo.",
                "ACAO" => "Transação não permitida. Selecionado a opção incorreta. Tente novamente. Se o erro persistir entre em contato com a Loja Virtual.",
            ],
            "KE" => [
                "DEFINICAO" => "Transação não autorizada. Falha na validação dos dados.",
                "SIGNIFICADO" => "Transação não autorizada. Falha na validação dos dados. Opção selecionada não está habilitada. Verifique as opções disponíveis para o portador.",
                "ACAO" => "Transação não autorizada. Falha na validação dos dados. Opção selecionada não está habilitada. Entre em contato com a loja virtual.",
            ],
            "N7" => [
                "DEFINICAO" => "Transação não autorizada. Código de segurança inválido.",
                "SIGNIFICADO" => "Transação não autorizada. Código de segurança inválido. Oriente o portador corrigir os dados e tentar novamente.",
                "ACAO" => "Transação não autorizada. Reveja os dados e informe novamente.",
            ],
            "R1" => [
                "DEFINICAO" => "Transação não autorizada. Cartão inadimplente (Do not honor).",
                "SIGNIFICADO" => "Transação não autorizada. Não foi possível processar a transação. Questão relacionada a segurança, inadimplencia ou limite do portador.",
                "ACAO" => "Transação não autorizada. Entre em contato com seu banco emissor.",
            ],
            "U3" => [
                "DEFINICAO" => "Transação não permitida. Falha na validação dos dados.",
                "SIGNIFICADO" => "Transação não permitida. Houve uma falha na validação dos dados. Solicite ao portador que reveja os dados e tente novamente. Se o erro persistir verifique a comunicação entre loja virtual e Cielo.",
                "ACAO" => "Transação não permitida. Houve uma falha na validação dos dados. reveja os dados informados e tente novamente. Se o erro persistir entre em contato com a Loja Virtual.",
            ],
        ];

        if (is_numeric($returnCode)) {
            $returnCode = intval($returnCode);
        }
        if (isset($status[$returnCode])) {
            return $status[$returnCode];
        }

        return [];
    }

    /**
     * Function ajax
     *
     * @return void
     */
    public static function ajax() {

    }

    /**
     * Function cielo_amount
     *
     * @param $value
     * @return int
     */
    private static function cielo_amount($value): int {
        return (int) round(formater::price_to_float($value) * 100);
    }
}