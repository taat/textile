<?php

require_once '../library/Vendor/Textile.php';

/**
 * Helps to test if Textile works ok
 *
 * @todo Provide complex $tester string to test all use cases of Textile
 */
class Textile_Test_Form {

    public $tester = 'h3. Test Textile header';

    /**
     * Forms
     *
     * @param string $textile
     * @param string $html
     * @param string $plain
     * @return string
     */
    public function get($textile = null, $html = '', $plain = '')
    {

        if ($textile === null) {
            $textile = $this->tester;
        }

        return '<form id="textile_test_form" method="post">
    <fieldset>
        <legend>Textile test</legend>
        <div>
        <label for="textile">Textile</label>
<textarea id="textile" name="textile" rows="7" cols="50">'.$textile.'</textarea>
        </div>

        <div>
        <label for="html">HTML</label>
<textarea id="html" name="html" rows="7" cols="50">'.$html.'</textarea>
        </div>

        <div>
        <label for="plain">Plain text</label>
<textarea id="plain" name="plain" rows="7" cols="50">'.$plain.'</textarea>
        </div>

        <input type="submit" name="submit" id="submit" value="Convert" />
    </fieldset>
</form>
';
    }

    /**
     * Was form posted?
     *
     * @return bool
     */
    public function posted()
    {
        return
            isset($_REQUEST['submit']) && !empty($_REQUEST['submit']) &&
            isset($_REQUEST['textile']) && !empty($_REQUEST['textile']);
    }

} // end Textile_Test class


$form = new Textile_Test_Form;
$textile = new Textile;

if (!$form->posted()) {
    echo $form->get();
} else {
    $text = $_REQUEST['textile'];
    echo $form->get($text, $textile->getHtml($text), $textile->getPlain($text));
}

?>
