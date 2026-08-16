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
 * PagseguroUtil.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios\util;

class PagseguroUtil {
    public static function getPaymentMethod_code($paymentMethodcode) {
        $code = [];
        $code[101] = 'Cartão de crédito Visa';
        $code[102] = 'Cartão de crédito MasterCard';
        $code[103] = 'Cartão de crédito American Express';
        $code[104] = 'Cartão de crédito Diners';
        $code[105] = 'Cartão de crédito Hipercard';
        $code[106] = 'Cartão de crédito Aura';
        $code[107] = 'Cartão de crédito Elo';
        $code[108] = 'Cartão de crédito PLENOCard';
        $code[109] = 'Cartão de crédito PersonalCard';
        $code[110] = 'Cartão de crédito JCB';
        $code[111] = 'Cartão de crédito Discover';
        $code[112] = 'Cartão de crédito BrasilCard';
        $code[113] = 'Cartão de crédito FORTBRASIL';
        $code[201] = 'Boleto Bradesco';
        $code[202] = 'Boleto Santander';
        $code[301] = 'Débito online Bradesco';
        $code[302] = 'Débito online Itaú';
        $code[303] = 'Débito online Unibanco';
        $code[304] = 'Débito online Banco do Brasil';
        $code[305] = 'Débito online Banco Real';
        $code[306] = 'Débito online Banrisul';
        $code[307] = 'Débito online HSBC';
        $code[401] = 'Saldo PagSeguro';
        $code[501] = 'Oi Paggo';

        return @$code[intval($paymentMethodcode)];
    }

    public static function getPaymentMethod_type($paymentMethodtype) {
        $code = [];
        $code[1] = 'Cartão de crédito';
        $code[2] = 'Boleto';
        $code[3] = 'Débito online (TEF)';
        $code[4] = 'Saldo PagSeguro';
        $code[5] = 'Oi Paggo';
        $code[7] = 'Depósito em conta';

        return $code[intval($paymentMethodtype)];
    }

    public static function getStatus($status) {
        $code = [];
        $code[0] = 'Iniciada';
        $code[1] = 'Aguardando pagamento';
        $code[2] = 'Em análise';
        $code[3] = 'Paga';
        $code[4] = 'Disponível';
        $code[5] = 'Em disputa';
        $code[6] = 'Devolvida';
        $code[7] = 'Cancelada';

        // Assinatura do pagSeguro
        $code['INITIATED'] = 'Iniciada';
        $code['PENDING'] =
            'O comprador iniciou o pagamento da assinatura ou optou por trocar o cartão de crédito atrelado a uma assinatura existente mas até o momento o PagSeguro não recebeu nenhuma confirmação da operadora responsável pelo processamento da transação validadora ou ela ainda está em análise.';
        $code['ACTIVE'] = 'A transação que originou a assinatura foi paga.';
        $code['CANCELLED'] = 'A transação foi cancelada por não ter sido aprovada pelo PagSeguro ou pela operadora de cartão.';
        $code['CANCELLED_BY_RECEIVER'] = 'A assinatura foi cancelada mediante solicitação do vendedor.';

        if (is_numeric($status)) {
            return $code[intval($status)];
        } else {
            return $code[$status];
        }
    }

    public static function getStatusV4($status) {
        $code = [];
        $code['AUTHORIZED'] = 'Pagamento está pré-autorizada.';
        $code['PAID'] = 'Pagamento está paga.';
        $code['WAITING'] = 'Pagamento está aguardando retorno do Boleto.';
        $code['IN_ANALYSIS'] =
            'Comprador optou por pagar com um Cartão de Crédito e o PagBank está analisando o risco da transação.';
        $code['DECLINED'] = 'Pagamento foi negado pelo PagBank ou Emissor do Cartão.';
        $code['CANCELED'] = 'Pagamento foi cancelado.';

        $code['OVERDUE'] = 'Pagamento da fatura está atrasada.';
        $code['UNPAID'] = 'Pagamento não foi realizado.';
        $code['REFUNDED'] = 'Pagamento foi estornado.';
        $code['DENIED'] = 'Pagamento foi negado.';

        if (isset($code[$status])) {
            return $code[$status];
        }
        return "({$status})";
    }
}