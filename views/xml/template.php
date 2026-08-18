<?= '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL; ?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">

    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1</cbc:CustomizationID>
    <cbc:ID><?= $invoice_data['INVOICE_ID'] ?></cbc:ID>
    <cbc:IssueDate><?= $invoice_data['INVOICE_DATE'] ?></cbc:IssueDate>
    <cbc:DueDate><?= $invoice_data['INVOICE_DUE_DATE'] ?></cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <?php foreach($invoice_data['INVOICE_NOTES'] as $note): ?>
    <cbc:Note><?= $note['NOTE'] ?></cbc:Note>
    <?php endforeach; ?>
    <cbc:DocumentCurrencyCode><?= $invoice_data['CURRENCY_CODE'] ?></cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode><?= $invoice_data['TAX_CURRENCY_CODE'] ?></cbc:TaxCurrencyCode>

    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:EndpointID schemeID="EM"><?= $invoice_data['COMPANY_EMAIL'] ?></cbc:EndpointID>
            <cac:PartyIdentification>
                <cbc:ID><?= $invoice_data['COMPANY_ID_NUMBER'] ?></cbc:ID>
            </cac:PartyIdentification>
            <cac:PostalAddress>
                <cbc:StreetName><?= $invoice_data['COMPANY_ADDRESS'] ?></cbc:StreetName>
                <cbc:CityName><?= $invoice_data['COMPANY_CITY'] ?></cbc:CityName>
                <cbc:CountrySubentity><?= $invoice_data['COMPANY_STATE'] ?></cbc:CountrySubentity>
                <cac:Country><cbc:IdentificationCode><?= $invoice_data['COMPANY_COUNTRY_ISO2'] ?></cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID><?= $invoice_data['COMPANY_VAT_NUMBER'] ?></cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName><?= $invoice_data['COMPANY_NAME'] ?></cbc:RegistrationName>
                <cbc:CompanyID><?= $invoice_data['COMPANY_REG_NUMBER'] ?></cbc:CompanyID>
                <cbc:CompanyLegalForm><?= $invoice_data['COMPANY_LEGAL_FORM'] ?></cbc:CompanyLegalForm>
            </cac:PartyLegalEntity>
            <cac:Contact>
                <?php if ($invoice_data['COMPANY_CONTACT_NAME'] !== ''): ?>
                <cbc:Name><?= $invoice_data['COMPANY_CONTACT_NAME'] ?></cbc:Name>
                <?php endif; ?>
                <cbc:Telephone><?= $invoice_data['COMPANY_CONTACT_PHONE'] ?></cbc:Telephone>
                <cbc:ElectronicMail><?= $invoice_data['COMPANY_EMAIL'] ?></cbc:ElectronicMail>
            </cac:Contact>
        </cac:Party>
    </cac:AccountingSupplierParty>

    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID><?= $invoice_data['CUSTOMER_ID'] ?></cbc:ID>
            </cac:PartyIdentification>
            <cac:PostalAddress>
                <cbc:StreetName><?= $invoice_data['INVOICE_BILLING_ADRESS'] ?></cbc:StreetName>
                <cbc:CityName><?= $invoice_data['INVOICE_BILLING_CITY'] ?></cbc:CityName>
                <cbc:CountrySubentity><?= $invoice_data['INVOICE_BILLING_STATE'] ?></cbc:CountrySubentity>
                <cac:Country><cbc:IdentificationCode><?= $invoice_data['INVOICE_BILLING_COUNTRY_ISO2'] ?></cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID><?= $invoice_data['CUSTOMER_VAT_NUMBER'] ?></cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName><?= $invoice_data['CUSTOMER_NAME'] ?></cbc:RegistrationName>
                <cbc:CompanyID><?= $invoice_data['CUSTOMER_VAT_NUMBER'] ?></cbc:CompanyID>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>

    <cac:PaymentMeans>
        <cbc:PaymentMeansCode><?= $invoice_data['PAYMENT_MEANS_CODE'] ?></cbc:PaymentMeansCode>
        <cac:PayeeFinancialAccount>
            <cbc:ID><?= $invoice_data['PAYMENT_IBAN'] ?></cbc:ID>
            <cbc:Name><?= $invoice_data['PAYMENT_BANK_NAME'] ?></cbc:Name>
        </cac:PayeeFinancialAccount>
    </cac:PaymentMeans>

    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $invoice_data['INVOICE_TOTAL_TAX'] ?></cbc:TaxAmount>
        <?php foreach($invoice_data['TAX_SUBTOTALS'] as $subtotal): ?>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $subtotal['TAXABLE_AMOUNT'] ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $subtotal['TAX_AMOUNT'] ?></cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent><?= $subtotal['TAX_RATE'] ?></cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
        <?php endforeach; ?>
    </cac:TaxTotal>

    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $invoice_data['INVOICE_SUBTOTAL'] ?></cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $invoice_data['INVOICE_SUBTOTAL'] ?></cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $invoice_data['INVOICE_TOTAL'] ?></cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $invoice_data['INVOICE_BALANCE_DUE'] ?></cbc:PayableAmount>
    </cac:LegalMonetaryTotal>

    <?php foreach($invoice_data['LINE_ITEMS'] as $item): ?>
    <cac:InvoiceLine>
        <cbc:ID><?= $item['LINE_ITEM_ORDER'] ?></cbc:ID>
        <cbc:InvoicedQuantity unitCode="<?= $item['LINE_ITEM_QUANTITY_UNIT'] ?>"><?= $item['LINE_ITEM_QUANTITY_NUMBER'] ?></cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $item['LINE_ITEM_TOTAL'] ?></cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Description><?= $item['LINE_ITEM_DESCRIPTION'] ?></cbc:Description>
            <cbc:Name><?= $item['LINE_ITEM_NAME'] ?></cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent><?= $item['TAX_RATE'] ?></cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="<?= $invoice_data['CURRENCY_CODE'] ?>"><?= $item['LINE_ITEM_UNIT_PRICE'] ?></cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
    <?php endforeach; ?>
</Invoice>
