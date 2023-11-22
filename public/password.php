<?php
/********************************************************************
KeaKeeper

Copyright (C) 2017 DesigNET, INC.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
********************************************************************/


require "../bootstrap.php";

/*************************************************************************
* Class          : Login
* Description    : Class for login page
* args           : None
* return         : true or false
*************************************************************************/
class Password {
    private $tags;
    private $store;

    /*************************************************************************
    * Method        : __construct
    * Description   : Method for setting tags automatically
    * args          : None
    * return        : None
    **************************************************************************/
    public function __construct($store)
    {
        $this->store = $store;

        /* initialized tags */
        $this->tags = ["e_passwd" => "",
                       "e_confirm" => "",
                       "success" => "",
                      ];
    }

    
    /*************************************************************************
    * Method        : modify
    * Description   : Password modifier
    * args          : None
    * return        : true/false
    **************************************************************************/
    public function modify($post)
    {
        $ret = $this->validate($post);
        if ($ret === false) {
            return false;
        }

        $db = new dbutils($this->store->db);

        $hash = sha1($post["passwd"]);

        $db->from("auth");
        $db->set([["password"=>"'$hash'"]]);
        $db->where(["user"=>$_SESSION["login_id"]]);
        $db->update();

        $this->tags["success"] = _("Password changed.");
    }

    /*************************************************************************
    * Method        : validate
    * Description   : Password chaker
    * args          : None
    * return        : true/false
    **************************************************************************/
    public function validate($post)
    {
        /* validation rules*/
        $rules["passwd"] = ["method"=>"exist|min:4|max:128|regex:/^[0-9a-zA-Z!#$%&()=~{}\[\]@*:+?.><,]*$/",
                            "msg"=>[
                                      _("Please enter Password."),
                                      _("Password too short."),
                                      _("Password too long."),
                                      _("Invalid password."),

                                     ],
                            "log"=>[
                                       "Password empty.",
                                       "Password too short",
                                       "Password too long",
                                       "Invalid password",
                                     ],
                             ];

        $rules["confirm"] = ["method"=>"exist|eq:passwd",
                             "msg"=>[
                                     _("Please enter confirm password."),
                                     _("Password does not match."),
                                    ],
                              "log"=>[
                                     "Confirm password empty.",
                                     "Password does not match.",
                                     ],
                             ];
        /* validation*/
        $validater = new validater($rules, $post, true);

        /* set display tags  */
        $this->tags = array_merge($this->tags, $validater->tags);

        /* validation error */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen.
    * args          : None
    * return        : None
    **************************************************************************/
    public function display()
    {
        $this->store->view->render("password.tmpl", $this->tags);
    }
}

/*************************************************************************
*  main
*************************************************************************/
$controller = new Password($store);
$post["passwd"] = post("passwd");
$post["confirm"] = post("confirm");

/**********************************
* modify section
***********************************/
if ($post["passwd"] !== null && $post["confirm"] !== null) {
    $controller->modify($post);
}

/**********************************
* Default section
***********************************/
$controller->display($store);
exit(0);
