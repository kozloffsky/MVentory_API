<?php
class MVentory_Tm_Block_Form_Element_Category extends Varien_Data_Form_Element_Hidden
{
    private $_cols = 0;
    
    private $_categories = array();
    
    public function getAfterElementHtml()
    {
        $html = parent::getAfterElementHtml();
        
        $cache = Mage::getSingleton('core/cache');
        $json = $cache->load('mventory_categories');

        if (!$json) {
            $json = file_get_contents('http://api.trademe.co.nz/v1/Categories.json');
            $cache->save($json, 'mventory_categories', array('mventory_categories'), 9999999999);
        }

        $json = Zend_Json::decode($json);
        $this->_parseCategories($json['Subcategories']);
        
        $html .= '<strong>Tm Category:</strong> ';
        
        if ($this->getValue() && isset($this->_categories[$this->getValue()])) {
            $html .= '<span id="tm_category_name">' . str_replace('#', ' - ', $this->_categories[$this->getValue()]) . '</span>';
            
            $cache = Mage::getSingleton('core/cache');
            $xml = $cache->load('mventory_attributes_' . $this->getValue());
            
            if (!$xml) {
                $xml = file_get_contents('http://api.trademe.co.nz/v1/Categories/' . $this->getValue() . '/Attributes.xml');
                $cache->save($xml, 'mventory_attributes_' . $this->getValue(), array('mventory_attributes_' . $this->getValue()), 9999999999);
            }
            
            $xml = simplexml_load_string($xml);
            
            $html .= '<div id="tm_category_attributes">';
            $html .= '<strong>Tm Attributes:</strong>';
            
            if ($xml) {    
                if (count($xml)) {                        
                    foreach ($xml as $attribute) {
                        $html .= '<br />&nbsp;' . strtolower($attribute->Name) . ' (' . $attribute->Type . ')';
                    }
                } else {
                    $html .= '<br />none';
                }
            } else {
                $html .= '<br />none';
            }
            
            $html .= '</div>';
        } else {
            $html .= '<span id="tm_category_name">None</span>';
            $html .= '<div id="tm_category_attributes"><strong>Tm Attributes:</strong><br />none</div>';
        }       
        
        $html .= '<br /><table cellspacing="0" cellpadding="0" class="massaction"><tbody><tr><td>
        <div class="right"><div class="entry-edit">
            <form id="tm_categories_filter_form" method="post" action=""><fieldset>
                <span class="field-row"><input id="tm_categories_filter" type="text" value="" /></span>
                <span class="field-row"><button id="tm_categories_button" class="scalable" type="button"><span>Filter</span></button></span></fieldset>
            </form>
        </div></div>
        </td></tr></tbody></table>';
        
        $html .= '<div class="grid"><div class="hor-scroll"><table id="tm_categories" class="data" cellspacing="0"><tbody>';
        
        $style = '';
        if (!$this->getValue() || !isset($this->_categories[$this->getValue()])) {
            $style = ' style="background: #F5D6C7;"';
        }
        
        $html .= '<tr id="tm_category_0"' . $style . '><td colspan="' . $this->_cols . '">None</td></tr>';
        
        $counter = 1;
        
        foreach ($this->_categories as $id => $path) {
            $class = 'pointer';
            
            if ($counter % 2) {
                $class = 'even ' . $class;
            }
            
            
            $style = '';
            if ($this->getValue() == $id) {
                $style = ' style="background: #F5D6C7;"';
            }
            
            $html .= '<tr id="tm_category_' . $id . '" class="' . $class . '"' . $style . '>';
            
            $path = explode('#', $path);
            
            foreach ($path as $name) {
                $html .= '<td>' . $name . '</td>';
            }
            
            if (count($path) < $this->_cols) {
                for ($i = 1; $i <= $this->_cols - count($path); $i++) {
                    $html .= '<td></td>';
                }
            }
            
            $html .= '</tr>';
            
            $counter++;
        }
        
        $html .= '</tbody></table></div></div>';
        
        
        $html .= '<script>
        var tm_t;    

        function tm_key_down() {
            if (tm_t) {
                clearTimeout(tm_t);
                tm_t = setTimeout(tm_categories_filter, 1000);
            } else {
                tm_t = setTimeout(tm_categories_filter, 1000);
            }
        }

        function tm_categories_filter()
        {
            var q = $("tm_categories_filter").value;
            var tmp = 1;

            $A($("tm_categories").getElementsByTagName("tr")).each(function(tr) {
                var display = 0;

                if (q.length) {
                    $A(tr.getElementsByTagName("td")).each(function(td) {
                        if (td.innerHTML.toLowerCase().indexOf(q.toLowerCase()) != -1) {
                            display = 1;
                        }
                    });
                } else {
                    display = 1;
                }

                if (display) {
                    tr.style.display = "";
                    
                    /*if (tmp) {
                        tr.style.className = tr.style.className.replace("even ", "");
                    } else {
                        if (tr.style.className.indexOf("even ") != -1) {
                            tr.style.className += "even ";
                        }
                    }
                    
                    tmp *= -1;*/
                } else {
                    tr.style.display = "none";
                }
            });
        }
        
        $("tm_categories_filter").onkeyup = function() { 
            tm_key_down(); 
        }

        $("tm_categories_button").onclick = function() { 
            tm_categories_filter(); 
        }

        $A($("tm_categories").getElementsByTagName("tr")).each(function(tr) {
            tr.onmouseover = function() {
                if (this.className.indexOf(" on-mouse") == -1) {
                    this.className += " on-mouse";
                }
            }
            
            tr.onmouseout = function() {
                this.className = this.className.replace(" on-mouse", "");
            }
            
            tr.onclick = function() {
                $A($("tm_categories").getElementsByTagName("tr")).each(function(tr) {
                    tr.style.background = "";
                });
                
                this.style.background = "#F5D6C7";

                var name = "";
                
                $A(this.getElementsByTagName("td")).each(function(td) {
                    if (td.innerHTML.length) {
                        if (name.length) {
                            name += " - ";
                        }
                        
                        name += td.innerHTML;
                    }
                });

                $("tm_category_name").innerHTML = name;
                $("' . $this->getHtmlId() . '").value = this.id.replace("tm_category_", "");
                $("tm_category_attributes").innerHTML = "<div id=\"tm_category_attributes\"><strong>Tm Attributes:</strong><br />retrieved on save</div>";
            }
        });
        </script>';
        
        return $html;
    }
    
    
    private function _parseCategories($subcategories, $path = '') {
        foreach ($subcategories as $category) {
            if (count($category['Subcategories'])) {
                $this->_parseCategories($category['Subcategories'], $path . $category['Name'] . '#');
            } else {
                $id = explode('-', $category['Number']);
                
                $this->_categories[$id[count($id) - 2]] = $path . $category['Name'];
                
                $tmp = count(explode('#', $path));
                if ($tmp > $this->_cols) {
                    $this->_cols = $tmp;
                }
            }
        }
    }
}