<?php
interface models_InterfacePayment
{
    public function install();
    public function uninstall();
    public function hookOrderConfirmation(array $params);
    public function hookBackOfficeHeader();
    public function getContent();
    public function hookPayment($params);
    public function hookHeader();
}