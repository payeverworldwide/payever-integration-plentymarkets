<?php

namespace Payever\Helper;

class CompanySearchHelper
{
    /**
     * @param $company
     * @param $email
     * @param $town
     * @param $zip
     * @return string
     */
    public function generateAddressHash($company, $email, $town, $zip): string
    {
        $params = [
            'company' => $company,
            'email' => $email,
            'town' => $town,
            'zip' => $zip,
        ];

        return hash('sha256', json_encode($params));
    }
}
