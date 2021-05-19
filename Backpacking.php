<?php

#------------------------------------------------------------------------------
define('DATAFILE_NAME'  , '/home/pi/Share/Data/jrw/Checklists/BackpackingData.xml');
define('BACKUPFILE_NAME', 'temp/BackpackingData.xml~');

#------------------------------------------------------------------------------
#copy the html to the output
function copy_to_html($html_array)
{
    foreach ($html_array as $line) {
        if (gettype($line) === 'array') {
            # an array, handle it recursively
            copy_to_html($line);
        } else if (gettype($line) === 'string') {
            if (is_callable($line)) {
                # line is function reference, call it
                $line();
            } else {
               # copy the line to the output
                echo "$line\n";
            }
        } else {
            echo '<p>Unknown data type in copy_to_html - ', gettype($line), '</p>';
        }
    }
}

#------------------------------------------------------------------------------
$XML = simplexml_load_file(DATAFILE_NAME);
if ($XML === false) {
    echo "Failed loading XML\n";
}

#------------------------------------------------------------------------------
function output_category_options() {
    global $XML;
    foreach ($XML->category as $cat) {
        $category = $cat->attributes();
        echo "<option value=\"", $category['name'], "\">", $category['name'], "</option>\n";
    }
}

#------------------------------------------------------------------------------
$add_component_html = array(
    '<label>&nbsp;Name&nbsp;</label>',
    '<input type="text" name="itemcomponent[]" size="50">',
    '<label>&nbsp;Ounces&nbsp;</label>',
    '<input',
    ' type="number" min="0.01" step="0.01"',
    ' name="itemcomponentweight[]" style="width:60px"',
    '>',
    '<br>',
);

#------------------------------------------------------------------------------
function output_page_title() {
    if (isset($_REQUEST['PrintView'])) {
        echo 'Print Backpack Inventory';
    } else {
        echo 'Backpack Inventory';
    }
}

#------------------------------------------------------------------------------
function error_message() {
    global $ErrorMessage;
    if (isset($ErrorMessage)) {
        copy_to_html(array(
                       "<br><br>",
                       "<div class=\"alert\">",
                       "<span class=\"closebutton\">&times;</span>",
                       "<strong>$ErrorMessage</strong>",
                       "</div>",
                      )
        );
    }
}

#------------------------------------------------------------------------------
function output_inventory() {
    global $XML;
    if (!isset($_REQUEST['PrintView'])) {
        copy_to_html(array(
                    '<div class="center_buttons">',
                    '<form action="">',
                    '<input type="submit" class="push_button" formtarget="_blank" name="PrintView" value="Print View" >',
                    '</form>',
                        )
        );
    }
    echo "<form action=\"\">\n";
    if (!isset($_REQUEST['PrintView'])) {
        copy_to_html(array(
                    '<input type="submit" class="push_button"  name="SaveChanges" value="Save" disabled="1"',
                    ' onclick="mark_unchanged_fields()"',
                    '>',
                    '</div>',
                    '<br>',
                        )
                    );
    }
    copy_to_html(array(
                    '<style>',
                    '.cols {',
                    '   display: flex;',
                    '   width: 100%;',
                    '}',
                    '.cols div {',
                    '   flex-grow: 1;',
                    '}',
                    '</style>',
                )
    );
    $categories = [];
    foreach ($XML->category as $cat) {
        $lines = 0;
        foreach ($cat->item as $item) {
            $lines++;
            if (isset($item->components->item)) {
                foreach ($item->components->item as $comp) {
                    $lines++;
                }
            }
        }
        array_push($categories, array("category"=>$cat, "lines"=>$lines));
    }
    function cmp($a, $b) {
        return $b['lines'] - $a['lines'];
    }
    usort($categories, "cmp");
    $columns = 0;
    foreach ($categories as $c) {
        $cat = $c['category'];
        if ($columns == 0) {
            echo "<div class=\"cols\">\n";
        }
        $category = $cat->attributes();
        echo "<div class=\"category_list\">\n";
        echo "<p style=\"font-size: x-large\">";
        echo $category['name'], " <span id=\"", $category['name'], "\"></span> lbs</p>\n";
        foreach ($cat->item as $item) {
            $items = $item->attributes();

            $ounces = 0;
            if (isset($item->components->item)) {
                foreach ($item->components->item as $comp) {
                    $ounces += $comp['ounces'];
                }
            } else {
                $ounces = $items['ounces'];
            }

            if (!isset($_REQUEST['PrintView']) || strcmp($items['carry'], 'YES') == 0) {
                echo "<div>\n";
                echo "<input value=1 name=\"", $category['name'], "\\", $items['name'], "\"\n";
                echo "    type=\"checkbox\" ", (strcmp($items['carry'], 'YES') == 0) ? "checked" : "";
                echo "    data-category=\"", $category['name'], "\"\n";
                if (isset($_REQUEST['PrintView'])) {
                    echo "    style=\"visibility:hidden\"\n";
                }
                echo ">\n";

                echo "<label style=\"visibility:\"", $items['quantity'] != 1 ? "visible" : "hidden", ">", $items['quantity'], "</label>";

                echo "<label class=\"itemlabel\" ";
                echo "  data-ounces=\"", $ounces, "\"";
                echo "  data-quantity=\"", $items['quantity'], "\"";
                echo ">\n";
                echo $items['name'], "\n";
                echo "</label>\n";
                if (isset($item->components->item)) {
                    foreach ($item->components->item as $comp) {
                        echo "<label class=\"components\" data-ounces=\"", $comp['ounces'], "\">", $comp['cname'], "</label>\n";
                    }
                }
                echo "</div>\n";
            }
        }
        echo "</div>\n";
        if (++$columns == 4) {
            echo "</div>\n";
            $columns = 0;
        }
    }
    if ($columns != 0) {
        echo "</div>\n";
    }
    echo "</form>\n";
}

#------------------------------------------------------------------------------
function output_edit_form() {
    if (!isset($_REQUEST['PrintView'])) {
        global $add_component_html;
        copy_to_html(array(
                    '<form action="" onkeydown="return event.key != \'Enter\';">',
                    '<table class="center_table" style="width:50%" border="2px solid black">',
                        '<tr>',
                            '<td>',
                                '<label for="categoryname">Category</label>',
                            '</td>',
                            '<td>',
                                '<select id="categoryname" name="categoryname" style="width:95%">',
                                    'output_category_options',
                                '</select>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td>',
                                '<label for="itemname">Item</label>',
                            '</td>',
                            '<td>',
                                '<label>&nbsp;Name&nbsp;</label>',
                                '<input type="text" id="itemname" name="itemname" size="50">',
                                '<label>&nbsp;Ounces&nbsp;</label>',
                                '<input',
                                ' type="number" min="0.01" step="0.01"',
                                ' id="itemweight" name="itemweight" style="width:60px"',
                                '>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td>',
                                '<label for="itemcomponent[]">Components</label>',
                            '</td>',
                            '<td>',
                                $add_component_html,
                                $add_component_html,
                                $add_component_html,
                                $add_component_html,
                                $add_component_html,
                                $add_component_html,
                                $add_component_html,
                                $add_component_html,
                                $add_component_html,
                                $add_component_html,
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td>',
                                '<label for="itemquantity">Quantity</label>',
                            '</td>',
                            '<td>',
                                '<input type="number" min="1" id="itemquantity" name="itemquantity" value="1" style="width:95%">',
                            '</td>',
                        '</tr>',
                    '</table>',
                    '<br>',
                    '<div class="center_buttons">',
                    '<input type="submit" class="push_button" name="AddItem"    value="Add or Update Item" disabled="1"',
                        ' onclick="mark_empty_components()"',
                    '>',
                    '<input type="submit" class="push_button" name="DeleteItem" value="Delete Item" disabled="1"',
                        ' onclick="mark_empty_components()"',
                    '>',
                    '</div>',
                    '</form> ',
                    '<script>',
                    '/*##########################################################################*/',
                    'function UpdateAddWeights() {',
                    '    var components = document.getElementsByName("itemcomponent[]");',
                    '    var componentweights = document.getElementsByName("itemcomponentweight[]");',
                    '    var addweight = document.getElementById("itemweight");',
                    '    var totalweight = 0;',

                    '    addweight.disabled = false;',
                    '    for (var i = 0; i < components.length; i++){',
                    '        if (components[i].value !== "") {',
                    '            addweight.disabled = true;',
                    '            componentweights[i].disabled = false;',
                    '            if (componentweights[i].value != "") {',
                    '                totalweight += parseFloat(componentweights[i].value);',
                    '            }',
                    '        } else {',
                    '            componentweights[i].value = "";',
                    '            componentweights[i].disabled = true;',
                    '        }',
                    '    }',
                    '    if (addweight.disabled) {',
                    '        addweight.value = totalweight.toFixed(2);',
                    '    }',
                    '    check_add_delete_fields();',
                    '}',

                    '/*##########################################################################*/',
                    'var addcomponents = document.getElementsByName("itemcomponent[]");',

                    'for (var i = 0; i < addcomponents.length; i++){',
                    '    addcomponents[i].addEventListener(\'change\', UpdateAddWeights);',
                    '}',

                    '/*##########################################################################*/',
                    'var addounces = document.getElementsByName("itemcomponentweight[]");',

                    'for (var i = 0; i < addounces.length; i++){',
                    '    addounces[i].addEventListener(\'change\', function() {',
                    '        var addweight = 0;',
                    '        var ComponentWeights = document.getElementsByName("itemcomponentweight[]");',
                    '        for (var j = 0; j < ComponentWeights.length; j++){',
                    '            if (ComponentWeights[j].value === "") break;',
                    '            addweight += parseFloat(ComponentWeights[j].value);',
                    '        }',
                    '        document.getElementById("itemweight").value = addweight.toFixed(2);',
                    '        check_add_delete_fields();',
                    '    });',
                    '}',

                    '/*##########################################################################*/',
                    'function is_non_blank(S) {',
                    '    return typeof(S) !== \'undefined\' && S !== \'\';',
                    '}',

                    '/*##########################################################################*/',
                    'function mark_empty_components() {',
                    '    var ComponentNames = document.getElementsByName("itemcomponent[]");',

                    '    for (var i = 0; i < ComponentNames.length; i++) {',
                    '        if (!is_non_blank(ComponentNames[i].value)) {',
                    '            ComponentNames[i].disabled = 1;',
                    '        }',
                    '    }',
                    '}',

                    '/*##########################################################################*/',
                    'function check_add_delete_fields() {',

                    '    itemname = document.getElementById("itemname");',
                    '    itemweight = document.getElementById("itemweight");',

                    '    var ComponentNames   = document.getElementsByName("itemcomponent[]");',
                    '    var ComponentWeights = document.getElementsByName("itemcomponentweight[]");',

                    '    enable = is_non_blank(itemname.value) && is_non_blank(itemweight.value);',

                    '    for (var i = 0; i < ComponentNames.length; i++) {',
                    '        if ( is_non_blank(ComponentNames[i].value) && !is_non_blank(ComponentWeights[i].value) ||',
                    '            !is_non_blank(ComponentNames[i].value) &&  is_non_blank(ComponentWeights[i].value)) {',
                    '            enable = false;',
                    '        }',
                    '    }',

                    '    if (enable) {',
                    '        document.getElementsByName("AddItem")[0].disabled = 0;',
                    '        document.getElementsByName("DeleteItem")[0].disabled = 0;',
                    '    } else {',
                    '        document.getElementsByName("AddItem")[0].disabled = 1;',
                    '        document.getElementsByName("DeleteItem")[0].disabled = 1;',
                    '    }',
                    '}',

                    'document.getElementById("itemname").addEventListener(  \'change\', check_add_delete_fields);',
                    'document.getElementById("itemweight").addEventListener(\'change\', check_add_delete_fields);',

                    '/*##########################################################################*/',
                    'var labels = document.getElementsByClassName("itemlabel");',

                    'for (var i = 0; i < labels.length; i++){',
                    '    labels[i].addEventListener( \'dblclick\', function() {',
                    '        var Label = this;',
                    '        var ChkBox = Label.parentNode.getElementsByTagName("input")[0];',
                    '        var components = Label.parentNode.getElementsByClassName("components")',
                    '        document.getElementById("categoryname").value = ChkBox.dataset.category;',
                    '        document.getElementById("itemname").value = this.innerHTML;',
                    '        document.getElementById("itemweight").value = this.dataset.ounces;',
                    '        document.getElementById("itemquantity").value = this.dataset.quantity;',
                    '        var ComponentNames   = document.getElementsByName("itemcomponent[]");',
                    '        var ComponentWeights = document.getElementsByName("itemcomponentweight[]");',
                    '        var j;',
                    '        var k = 0;',
                    '        for (j = 0; j < components.length; j++){',
                    '            ComponentNames[k].value = components[j].innerHTML;',
                    '            ComponentWeights[k].value = components[j].dataset.ounces;',
                    '            k++;',
                    '        }',
                    '        while (k < 10) {',
                    '            ComponentNames[k].value = "";',
                    '            ComponentWeights[k].value = "";',
                    '            k++;',
                    '        }',
                    '        UpdateAddWeights();',
                    '    });',
                    '}',
                    '</script>',
            )
        );
    }
}

#------------------------------------------------------------------------------
function write_xml_to_file() {
    global $ErrorMessage;
    global $XML;
    if (copy(DATAFILE_NAME, BACKUPFILE_NAME)) {
        $dom = new DOMDocument('1.0');
        $dom->loadXML($XML->asXML(), LIBXML_NOBLANKS);
        $dom->formatOutput = true;
        if (file_put_contents(DATAFILE_NAME, $dom->saveXML()) === false) {
            if (copy(BACKUPFILE_NAME, DATAFILE_NAME)) {
                $ErrorMessage = "Failed to write file";
            } else {
                $ErrorMessage = "Failed to restore back-up file";
            }
        }
    } else {
        $ErrorMessage = "Failed to create back-up file";
    }
}

#------------------------------------------------------------------------------
function fix_spaces($s) {
    # php stupidly replaces spaces with underscores, so undo it
    # this means you can't have an underscore in a category or item name
    return str_replace('_', ' ', $s);
}

#------------------------------------------------------------------------------
function validate_common_arguments() {
    global $ErrorMessage;
    if (!isset($_REQUEST['categoryname'])) {
        $ErrorMessage = "Invalid request - missing category";
    } else if (!isset($_REQUEST['itemname'])) {
        $ErrorMessage = "Invalid request - missing item name";
    } else if (!isset($_REQUEST['itemquantity'])) {
        $ErrorMessage = "Invalid request - missing quantity";
    } else {
        return true;
    }
    return false;
}

#------------------------------------------------------------------------------
function validate_components() {
    if (isset($_REQUEST['itemcomponent'])) {
        if (!isset($_REQUEST['itemcomponentweight'])) {
            $ErrorMessage = "Invalid add request - missing component weights";
            return false;
        } else if (sizeof($_REQUEST['itemcomponent']) != sizeof($_REQUEST['itemcomponentweight'])) {
            $ErrorMessage = "Invalid add request - mismatched components";
            return false;
        }
    }
    return true;
}

#------------------------------------------------------------------------------
function find_category($categoryname) {
    global $XML;
    foreach ($XML->category as $cat) {
        if (strcmp($cat['name'], $categoryname) == 0) {
            return $cat;
        }
    }
    return false;
}

#------------------------------------------------------------------------------
function find_item($categoryname, $itemname) {

    $cat = find_category($categoryname);
    if ($cat === false) return false;

    for ($i = 0; $i < sizeof($cat->item); $i++) {
        $attributes = $cat->item[$i]->attributes();
        if (strcmp($attributes['name'], $itemname) == 0) {
            return array('category' => $cat, 'itemindex' => $i);
        }
    }
    return false;
}

#------------------------------------------------------------------------------
if (isset($_REQUEST['SaveChanges'])) {
    foreach ($_REQUEST as $Key => $Value) {
        if (strcmp($Key, 'SaveChanges') != 0) {

            $Key = fix_spaces($Key);

            $s = preg_split("/\\\\/", $Key);
            if (sizeof($s) == 2) {
                if (($i = find_item($s[0], $s[1])) === false) {
                    $ErrorMessage = "Invalid item - \"" . $Key . "\"";
                } else {
                    $attributes = $i['category']->item[$i['itemindex']]->attributes();
                    $attributes['carry'] = ($Value == 0) ? 'NO' : 'YES';
                }
            } else {
                $ErrorMessage = "Invalid item - \"" . $Key . "\"";
                break;
            }
        }
    }
    write_xml_to_file();
} else if (isset($_REQUEST['PrintView'])) {
    #nothing to do here
} else if (isset($_REQUEST['AddItem'])) {

    function add_components($item) {
        $c = $item->addChild('components');
        for ($i = 0; $i < sizeof($_REQUEST['itemcomponent']); $i++) {
            $ci = $c->addChild('item');
            $ci->addAttribute('cname',  $_REQUEST['itemcomponent'][$i]);
            $ci->addAttribute('ounces', $_REQUEST['itemcomponentweight'][$i]);
        }
    }

    if (validate_common_arguments() && validate_components()) {
        $categoryname = fix_spaces($_REQUEST['categoryname']);
        $itemname     = fix_spaces($_REQUEST['itemname']);
        if (($i = find_item($categoryname, $itemname)) === false) {
            # add item
            $c = find_category($categoryname);
            $item = $c->addChild('item');
            $item->addAttribute('name', $itemname);
            $item->addAttribute('carry', 'YES');
            $item->addAttribute('quantity', $_REQUEST['itemquantity']);
            if (isset($_REQUEST['itemcomponent'])) {
                add_components($item);
            } else {
                $item->addAttribute('ounces', $_REQUEST['itemweight']);
            }
        } else {
            # update item
            $attributes = $i['category']->item[$i['itemindex']]->attributes();
            $attributes['quantity'] = $_REQUEST['itemquantity'];

            unset($i['category']->item[$i['itemindex']]->components);
            if (isset($_REQUEST['itemcomponent'])) {
                add_components($i['category']->item[$i['itemindex']]);
            } else {
                $attributes['ounces'] = $_REQUEST['itemweight'];
            }
        }
        write_xml_to_file();
    }
} else if (isset($_REQUEST['DeleteItem'])) {
    if (validate_common_arguments()) {
        $categoryname = fix_spaces($_REQUEST['categoryname']);
        $itemname     = fix_spaces($_REQUEST['itemname']);
        if (($i = find_item($categoryname, $itemname)) === false) {
            $ErrorMessage = "Invalid item - \"" . $categoryname . "/" . $itemname . "\"";
        } else {
            unset($i['category']->item[$i['itemindex']]);
            write_xml_to_file();
        }
    }
}

#------------------------------------------------------------------------------
copy_to_html(array(
    '<!DOCTYPE html',
    '	PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"',
    '	 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
    '<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US" xml:lang="en-US">',
    '<head>',
    '<title>', 'output_page_title', '</title>',
    '<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />',
    '</head>',

    '<body>',

    'error_message',

    '<br><br>',
    '<table class="center_table" style="width:80%" border="2px solid black">',
        '<tr>',
            '<th>',
                '<p style="font-size: x-large">Total  <span id="Total" ></span></p>',
            '</th>',
            '<th>',
                '<p style="font-size: x-large">In Pack <span id="In Pack"></span></p>',
            '</th>',
            '<th>',
                '<p style="font-size: x-large">Base   <span id="Base"  ></span></p>',
            '</th>',
        '</tr>',
    '</table>',
    '<br>',
    'output_inventory',
    '<br><br>',
    'output_edit_form',
    '<br><br>',

    '<style id="compiled-css" type="text/css">',

    '/*   ------------------------------------------------------------- */',
    '.category_list {',
    '    padding: 5px;',
    '    margin: 5px;',
    '    width: 25%;',
    '    border: 2px solid black;',
    '}',

    '.center_table {',
    '    margin-left: auto;',
    '    margin-right: auto;',
    '}',

    '.center_buttons {',
    '    display: flex;',
    '    justify-content: center;',
    '    align-items: center;',
    '}',

    '/*   ------------------------------------------------------------- */',
    '/*   CSS code for checkboxes with collapsible component lists      */',

    '.components{',
    '  margin-left: 50px;',
    '  display: none;',
    '}',

    '.components.active{',
    '  display: block;',
    '}',

    '/*   ------------------------------------------------------------- */',
    '/*   CSS code for error messages                                   */',
    '.alert {',
    '    padding: 20px;',
    '    background-color: red;',
    '    color: white;',
    '    opacity: 1;',
    '    transition: opacity 0.6s;',
    '    margin-bottom: 15px;',
    '}',

    '.closebutton {',
    '  margin-left: 15px;',
    '  color: white;',
    '  font-weight: bold;',
    '  float: right;',
    '  font-size: 22px;',
    '  line-height: 20px;',
    '  cursor: pointer;',
    '  transition: 0.3s;',
    '}',

    '.closebutton:hover {',
    '  color: black;',
    '}',

    '/*   ------------------------------------------------------------- */',
    '.itemlabel {',
    '    color: black;',
    '    font-size: large;',
    '}',

    '/*   ------------------------------------------------------------- */',
    '.push_button {',
    '	width:220px;',
    '	height:40px;',
    '   font-size: x-large;',
    '	text-align:center;',
    '	background: silver;',
    '	border:2px solid black;',
    '	border-radius:5px;',
    '   margin: 5px 5px 5px 5px;',
    '}',
    '.push_button:disabled {',
    '	background: lightgray;',
    '}',

    '/*   ------------------------------------------------------------- */',
    '</style>',

    '<script>',

    'function mark_unchanged_fields() {',
    '    var checks = document.querySelectorAll("input[type=checkbox]");',
    '    for (var i = 0; i < checks.length; i++){',
    '        if (checks[i].dataset.changed) {',
    '            if (checks[i].checked) {',
    '               checks[i].value = 1;',
    '            } else {',
    '               checks[i].checked = 1;', # won't submit unless checked
    '               checks[i].value = 0;',
    '            }',
    '        } else {',
    '            checks[i].checked = 0;', # don't submit unchanged values
    '        }',
    '    }',
    '}',

    'function updateTotals() {',
    '    var checks = document.querySelectorAll("input[type=checkbox]");',
    '    var category_totals = {};',
    '    var total_weight = 0;',
    '    var in_pack_weight = 0;',
    '    var base_weight = 0;',

    '    for (var i = 0; i < checks.length; i++){',
    '        if (checks[i].checked) {',
    '            var ChkLabel = checks[i].parentNode.getElementsByClassName("itemlabel")[0];',
    '            var Pounds = Math.round((parseFloat(ChkLabel.dataset.ounces) * parseInt(ChkLabel.dataset.quantity) / 16) * 100) / 100;',
    '            var Category = checks[i].dataset.category;',
    '            if (typeof category_totals[Category] === \'undefined\') {',
    '                category_totals[Category] = Pounds;',
    '            } else {',
    '                category_totals[Category] += Pounds;',
    '            }',
    '            total_weight += Pounds;',
    '            if (Category != "Not In Pack") {',
    '                in_pack_weight += Pounds;',
    '                if (Category != "Consumables") {',
    '                    base_weight += Pounds;',
    '                }',
    '            }',
    '        }',
    '    }',
    '    for (var Category in category_totals) {',
    '        document.getElementById(Category).innerHTML = category_totals[Category].toFixed(2);',
    '    }',
    '    document.getElementById(\'Total\').innerHTML = total_weight.toFixed(2);',
    '    document.getElementById(\'In Pack\').innerHTML = in_pack_weight.toFixed(2);',
    '    document.getElementById(\'Base\').innerHTML = base_weight.toFixed(2);',
    '}',

    '/*##########################################################################*/',
    'function displaySaveButtonMaybe() {',
    '    /* un-hide the "SAVE" button if changes were made */',
    '    var SaveButton = document.getElementsByName(\'SaveChanges\')[0];',
    '    SaveButton.disabled = 1;',
    '    var checks = document.querySelectorAll("input[type=checkbox]");',
    '    for (var i = 0; i < checks.length; i++){',
    '        if (checks[i].dataset.changed == 1) {',
    '            SaveButton.disabled = 0;',
    '            break;',
    '        }',
    '    }',
    '}',

    '/*##########################################################################*/',
    'var checks = document.querySelectorAll("input[type=checkbox]");',

    'for (var i = 0; i < checks.length; i++){',
    '    /* add an event listener for all checkboxes */',
    '    checks[i].addEventListener( \'change\', function() {',
    '        var ChkLabel = this.parentNode.getElementsByClassName("itemlabel")[0];',
    '        var OriginalCheckState = sessionStorage.getItem(this.name);',
    '        if (( this.checked && OriginalCheckState == 0) ||',
    '            (!this.checked && OriginalCheckState == 1)',
    '           )',
    '        {',
    '            ChkLabel.style.color = "red";',
    '            this.dataset.changed = 1;',
    '        } else {',
    '            ChkLabel.style.color = "black";',
    '            this.dataset.changed = 0;',
    '        }',
    '        if(this.checked) {',
    '             /* item was selected, add the weight to the totals',
    '              * and un-hide the children components',
    '              */',
    '             showComponents(this);',
    '        } else {',
    '             /* item was unselected, subtract the weight from the totals',
    '              * and hide the children components',
    '              */',
    '             hideComponents(this)',
    '        }',
    '        updateTotals();',
    '        displaySaveButtonMaybe();',
    '    });',
    '    /* show or hide the children of a checkbox (components)',
    '     * and save the initial value of the checkboxes',
    '     */',
    '    if (checks[i].checked) {',
    '        sessionStorage.setItem(checks[i].name, 1);',
    '        showComponents(checks[i]);',
    '    } else {',
    '        sessionStorage.setItem(checks[i].name, 0);',
    '        hideComponents(checks[i]);',
    '    }',
    '}',

    '/*##########################################################################*/',
    '/* un-hide the components of the checkbox that changed */',
    'function showComponents(ChkBox) {',
    '    var components = ChkBox.parentNode.getElementsByClassName("components");',
    '   ',
    '    for (var i = 0; i < components.length; i++){',
    '      components[i].classList.add("active");      ',
    '    }',
    '}',

    '/*##########################################################################*/',
    '/* hide the components of the checkbox that changed */',
    'function hideComponents(ChkBox) {',
    '    var components = ChkBox.parentNode.getElementsByClassName("components");',
    '   ',
    '    for (var i = 0; i < components.length; i++){',
    '      components[i].classList.remove("active");      ',
    '    }',
    '}',

    '/*##########################################################################*/',
    'var close = document.getElementsByClassName("closebutton");',
    'var i;',

    'for (i = 0; i < close.length; i++) {',
    '  close[i].onclick = function(){',
    '    var div = this.parentElement;',
    '    div.style.opacity = "0";',
    '    setTimeout(function(){ div.style.display = "none"; }, 600);',
    '  }',
    '}',

    '/*##########################################################################*/',
    'updateTotals();',

    '</script>',

    '</body>',
    '</html>',

    )
);

#------------------------------------------------------------------------------
?>

