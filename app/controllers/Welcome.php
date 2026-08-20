<?php
class Welcome extends BaseController
{
    public function index()
    {
        return $this->respond('/app/page/welcome.php');
    }
}
?>
