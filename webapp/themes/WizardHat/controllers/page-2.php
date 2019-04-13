<?php


	class MySecondController extends Wizard\Build\Controller {

		function validate() {
			//if (q('h') && a('view content')) return 1;
			if (q("page2")) return 1;
			else return false;
		}

		function execute() {
			// My first Model
			// args: $var, $val, $id=null, $parent_id=null, $namespace="general"
			$myModel = new \Wizard\Build\Model("title","My Second Page!", "general");
			$main_img = new \Wizard\Build\Model("headImgSrc","/theme/files/img/sections/tutorials-bg.jpg", "general");

			$myMatrix = $this->Matrix(); // default Models List
			$myMatrix->space("guest")->def("id", "email", "name");
			$myMatrix->add(1, "mr@email.com", "Mr. Admin")
			  ->add(2, "ms@email.com", "Ms. Adminette")
			  ->add(3, "bob@email.com", "Bob the Admin");

			// Load HTML(DOM) Skeleton
			$myView = new Wizard\Build\View(); // or $myView = $this->View();
			$myView->from("body.html"); // declare fetch to be a template by filename
			$myView->render();

			// Load HTML(DOM) Skeleton
			$content = new Wizard\Build\View(); // or $myView = $this->View();
			$content->from("page2-content.html"); // declare fetch to be a template by filename
			$content->to(".page-content"); // declare fetch to be a template by filename
			$content->display_mode("append"); // declare fetch to be a template by filename
			$content->render();

		}

	}
