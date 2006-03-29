<?php

/*
	This file is part of the Legal Case Management System (LCM).
	(C) 2004-2006 Free Software Foundation, Inc.

	This program is free software; you can redistribute it and/or modify it
	under the terms of the GNU General Public License as published by the 
	Free Software Foundation; either version 2 of the License, or (at your 
	option) any later version.

	This program is distributed in the hope that it will be useful, but 
	WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
	or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
	for more details.

	You should have received a copy of the GNU General Public License along 
	with this program; if not, write to the Free Software Foundation, Inc.,
	59 Temple Place, Suite 330, Boston, MA  02111-1307, USA

	$Id: inc_obj_exp.php,v 1.2 2006/03/29 17:24:01 mlutfy Exp $
*/

// Execute this file only once
if (defined('_INC_OBJ_EXPENSE')) return;
define('_INC_OBJ_EXPENSE', '1');

include_lcm('inc_obj_generic');

class LcmExpense extends LcmObject {
	// Note: Since PHP5 we should use "private", and generates a warning,
	// but we must support PHP >= 4.0.
	var $comments;
	var $comment_start_from;

	function LcmExpense($id_expense = 0) {
		$id_expense = intval($id_expense);
		$this->comments = null;
		$this->comment_start_from = 0;

		$this->LcmObject();

		if ($id_expense > 0) {
			$query = "SELECT * FROM lcm_expense WHERE id_expense = $id_expense";
			$result = lcm_query($query);

			if (($row = lcm_fetch_array($result))) 
				foreach ($row as $key => $val) 
					$this->data[$key] = $val;
		}

		// If any, populate form values submitted
		foreach($_REQUEST as $key => $value) {
			$nkey = $key;

			if (substr($key, 0, 8) == 'expense_')
				$nkey = substr($key, 8);

			$this->data[$nkey] = _request($key);
		}

		// If any, populate with session variables (for error reporting)
		if (isset($_SESSION['form_data'])) {
			foreach($_SESSION['form_data'] as $key => $value) {
				$nkey = $key;

				if (substr($key, 0, 8) == 'expense_')
					$nkey = substr($key, 8);

				$this->data[$nkey] = _session($key);
			}
		}

		if (! $this->getDataInt('id_expense'))
			$this->data['id_author'] = $GLOBALS['author_session']['id_author'];
	}

	/* private */
	function loadComments($list_pos = 0) {
		global $prefs;

		$q = "SELECT id_comment
				FROM lcm_expense_comment as ec
				WHERE id_expense = " . $this->getDataInt('id_expense', '__ASSERT__');

		// Sort cases by creation date
		$case_order = 'DESC';
		if (_request('expense_order') == 'ASC' || _request('expense_order') == 'DESC')
			$case_order = _request('expense_order');
		
		$q .= " ORDER BY ec.date_creation " . $case_order;

		$result = lcm_query($q);
		$number_of_rows = lcm_num_rows($result);
			
		if ($list_pos >= $number_of_rows)
			return;
				
		// Position to the page info start
		if ($list_pos > 0)
			if (! lcm_data_seek($result, $list_pos))
				lcm_panic("Error seeking position $list_pos in the result");

		if (lcm_num_rows($result)) {
			for ($cpt = 0; (($cpt < $prefs['page_rows']) && ($row = lcm_fetch_array($result))); $cpt++)
				array_push($this->comments, $row['id_comment']);
		}
	}

	function getCommentStart() {
		global $prefs;

		$start_from = _request('list_pos', 0);

		// just in case
		if (! ($start_from >= 0)) $start_from = 0;
		if (! $prefs['page_rows']) $prefs['page_rows'] = 10; 

		$this->comments = array();
		$this->comment_start_from = $start_from;
		$this->loadComments($start_from);
	}

	function getCommentDone() {
		return ! (bool) (count($this->comments));
	}

	function getCommentIterator() {
		global $prefs;

		if ($this->getCommentDone())
			lcm_panic("LcmComment::getCommentIterator called but getCommentDone() returned true");

		$ret = array_shift($this->comments);

		return $ret;
	}

	function getCommentTotal() {
		static $cpt_total_cache = null;

		if (is_null($cpt_total_cache)) {
			$query = "SELECT count(*) as cpt
					FROM lcm_expense_comment as ec
					WHERE ec.id_expense = " . $this->getDataInt('id_expense', '__ASSERT__');

			$result = lcm_query($query);

			if (($row = lcm_fetch_array($result)))
				$cpt_total_cache = $row['cpt'];
			else
				$cpt_total_cache = 0;
		}

		return $cpt_total_cache;
	}

	function validate() { // TODO
		$errors = array();

		if (!$this->getDataString('description'))
			$errors['description'] = _Ti('expense_input_description') . _T('warning_field_mandatory');

		//
		// Custom validation functions
		//

		// * other fields
		$id_expense = $this->getDataInt('id_expense');

		$fields = array('description' => 'ExpenseDescription'); 

		foreach ($fields as $f => $func) {
			if (include_validator_exists($f)) {
				include_validator($f);
				$class = "LcmCustomValidate$func";
				$data = $this->getDataString($f);
				$v = new $class();

				if ($err = $v->validate($id_client, $data)) 
					$errors[$f] = _Ti('expense_input_' . $f) . $err;
			}
		}

		return $errors;
	}

	//
	// Save client record in DB (create/update)
	// Returns array of errors, if any
	//
	function save() {
		$errors = $this->validate();

		if (count($errors))
			return $errors;

		//
		// Update record in database
		//
		$cl = "type   = '"      . $this->getDataString('type') . "',
			   cost   = "      . $this->getDataInt('cost') . ",
			   description = '" . $this->getDataString('description') . "',
			   date_update = 'NOW()',
			   pub_read  = 1,
			   pub_write = 1";
		
		// XXX add case where id_admin should be updated
		// XXX add status (user can delete, admin can grant/approve/delete)
	
		if ($this->getDataInt('id_expense') > 0) {
			$q = "UPDATE lcm_expense
				SET $cl 
				WHERE id_expense = " . $this->getDataInt('id_expense', '__ASSERT__');
		
			lcm_query($q);
		} else {
			$q = "INSERT INTO lcm_expense
					SET date_creation = NOW(), 
						id_admin    = 0,
						id_author   = " . $this->getDataInt('id_author') . ",
						id_followup = "  . $this->getDataInt('id_followup') . ",
						id_case     = "      . $this->getDataInt('id_case') . ",
						status      = 'pending',
						$cl";
	
			$result = lcm_query($q);
			$this->data['id_expense'] = lcm_insert_id('lcm_expense', 'id_expense');


			$comment = new LcmExpenseComment($this->data['id_expense'], 0);
			$comment->save();
		}

		return $errors;
	}
}

class LcmExpenseInfoUI extends LcmExpense {
	function LcmExpenseInfoUI($id_expense = 0) {
		$this->LcmExpense($id_expense);
	}

	function printGeneral($show_subtitle = true) {
		if ($show_subtitle)
			show_page_subtitle(_T('generic_subtitle_general'), 'expenses_intro');

		echo '<ul class="info">';
		echo '<li>' 
			. '<span class="label1">' . _Ti('expense_input_id') . '</span>'
			. '<span class="value1">' . $this->getDataInt('id_expense') . '</span>'
			. "</li>\n";

		echo '<li class="large">'
			. '<span class="label2">' . _Ti('expense_input_description') . '</span>' 
			. '<span class="value2">'. nl2br(clean_output($this->getDataString('description'))) . '</span>'
			. "</li>\n";

		echo '<li>'
			. '<span class="label2">' . _Ti('expense_input_cost') . '</span>'
			. '<span class="value2">' .  format_money($this->getDataInt('cost')) . '</span>'
			. "</li>\n";

		echo "</ul>\n";
	}

	function printComments($find_case_string = '') {
		$cpt = 0;
		$my_list_pos = intval(_request('list_pos', 0));

		show_page_subtitle(_T('expenses_subtitle_comments'), 'expenses_comments');

		for ($cpt = 0, $this->getCommentStart(); (! $this->getCommentDone()); $cpt++) {
			$id_comment = $this->getCommentIterator();

			$comment = new LcmExpenseCommentInfoUI($this->getDataInt('id_expense'), $id_comment);
			$comment->printGeneral();
		}

		if (! $cpt)
			echo "<p>No comments</p>"; // TRAD

		show_listcase_end($my_list_pos, $this->getCommentTotal());
	}

	function printEdit() {
		echo '<table width="99%" border="0" align="center" cellpadding="5" cellspacing="0" class="tbl_usr_dtl">' . "\n";

		// TODO:
		// Ajouter contexte
		// Ajouter auteur, etc.
		// Ajouter "approved by" si id_admin != 0
		
		if($this->getDataInt('id_expense')) {
			echo "<tr><td>" . _T('expense_input_id') . "</td>\n";
			echo "<td>" . $this->getDataInt('id_expense')
				. '<input type="hidden" name="id_expense" value="' . $this->getDataInt('id_expense') . '" /></td></tr>' . "\n";
		}

		echo '<tr><td>' . f_err_star('description') . _T('expense_input_description') . '</td>' . "\n";
		echo '<td><textarea name="description" id="input_expense_description" class="frm_tarea" rows="3" cols="60">'
			. clean_output($this->getDataString('description'))
			. "</textarea>\n"
			. "</td>\n";
		echo "</tr>\n";
		
		echo '<tr><td>' . f_err_star('type') . _T('expense_input_type') . '</td>' . "\n";
		echo '<td><input name="type" value="' . clean_output($this->getDataString('type')) . '" class="search_form_txt" /></td></tr>' . "\n";

		// TODO: add currency
		echo '<tr><td>' . f_err_star('cost') . _T('expense_input_cost') . '</td>' . "\n";
		echo '<td><input name="cost" value="' . $this->getDataInt('cost') . '" class="search_form_txt" /></td></tr>' . "\n";
		

		// Show comment box only if new expense (not edit)
		if (! $this->getDataInt('id_expense')) {
			echo "<tr>\n";
			echo "<td>" . f_err_star('comment') . _Ti('expense_input_comment') . "</td>\n";
			echo '<td><textarea name="comment" id="input_expense_comment" class="frm_tarea" rows="3" cols="60">'
				. clean_output($this->getDataString('comment'))
				. "</textarea>\n"
				. "</td>\n";
			echo "</tr>\n";
		}
	
		echo "</table>\n";
	}

}

class LcmExpenseComment extends LcmObject {
	function LcmExpenseComment($id_expense, $id_comment = 0) {
		$id_expense = intval($id_expense);
		$id_comment = intval($id_comment);

		$this->LcmObject();

		if ($id_comment > 0) {
			$query = "SELECT ec.*, ec.id_expense, a.name_first, a.name_middle, a.name_last
						FROM lcm_expense_comment as ec, lcm_author as a
						WHERE ec.id_comment = $id_comment
						  AND ec.id_expense = $id_expense
						  AND ec.id_author = a.id_author";

			$result = lcm_query($query);

			if (($row = lcm_fetch_array($result))) 
				foreach ($row as $key => $val) 
					$this->data[$key] = $val;
			else
				lcm_panic("Comment not found.");
		} else {
			$this->data['id_expense'] = $id_expense;
			$this->data['id_author'] = $GLOBALS['author_session']['id_author'];
		}

		// If any, populate form values submitted
		foreach($_REQUEST as $key => $value) {
			$nkey = $key;

			if (substr($key, 0, 8) == 'comment_')
				$nkey = substr($key, 8);

			$this->data[$nkey] = _request($key);
		}

		// If any, populate with session variables (for error reporting)
		if (isset($_SESSION['form_data'])) {
			foreach($_SESSION['form_data'] as $key => $value) {
				$nkey = $key;

				if (substr($key, 0, 8) == 'comment_')
					$nkey = substr($key, 8);

				$this->data[$nkey] = _session($key);
			}
		}

		if ($this->getDataInt('id_expense', '__ASSERT__') != $id_expense)
			lcm_panic("id_expense of object does not match comment id_expense");
	}

	function validate() {
		$errors = array();

		return $errors;
	}

	function save() {
		$errors = $this->validate();

		if (count($errors))
			return $errors;

		//
		// Update record in database
		//
		if ($this->getDataInt('id_comment') > 0) {
			$q = "UPDATE lcm_expense_comment SET 
					date_update = NOW(),
					comment = '" . $this->getDataString('comment') . "'
					WHERE id_expense = " . $this->getDataInt('id_expense') . "
					  AND id_comment = " . $this->getDataInt('id_comment');

			lcm_query($q);
		} else {
			$q = "INSERT INTO lcm_expense_comment " 
				. "(id_expense, id_author, date_creation, date_update, comment) "
				. "VALUES " 
				. "(" . $this->getDataInt('id_expense') . ", "
					  . $this->getDataInt('id_author') . ", "
					  . "NOW(),"
					  . "NOW(),"
					  . "'" . $this->getDataString('comment') . "'"
				. ")";

			lcm_query($q);
			$this->data['id_comment'] = lcm_insert_id('lcm_expense_comment', 'id_comment');
		}

		return $errors;
	}
}

class LcmExpenseCommentInfoUI extends LcmExpenseComment {
	function LcmExpenseCommentInfoUI($id_expense, $id_comment = 0) {
		$this->LcmExpenseComment($id_expense, $id_comment);
	}

	function getPerson() {
		return get_person_name($this->data);
	}

	function printGeneral() {
		echo "<div style='border-bottom: 1px solid #ccc;'>"; // CSS

		echo "<!-- ID: " . $this->getDataInt('id_comment') . "<br/ -->\n";

		echo '<p class="normal_text">'
			. get_author_link($this->data)
			. ' @ '
			. format_date($this->getDataString('date_creation'));

		if ($this->getDataString('date_update') != $this->getDataString('date_creation')) 
			echo ' (' . _Ti('time_input_date_updated') . format_date($this->getDataString('date_update')) . ')';

		echo "</p>\n";

		echo "<div style='float: right;'>";
		echo '<a title="Edit this comment" '
				. 'class="edit_lnk href="edit_exp.php?expense=' . $this->getDataInt('id_expense', '__ASSERT__') 
				. '&ct=' . $this->getDataInt('id_comment', '__ASSERT__')
				. '">' . _T('edit') . '</a>'; // TRAD
		echo "</div>\n";

		echo '<p class="normal_text">' . nl2br($this->getDataString('comment')) . "</p>\n";
		
		echo "</div>\n";
	}

	function printEdit() {
		$id_comment = $this->getDataInt('id_comment', 0);

		echo '<input type="hidden" name="edit_comment" value="1" />' . "\n";
		echo '<input type="hidden" name="id_expense" value="' . $this->getDataInt('id_expense') . '" />' . "\n";

		if ($id_comment) {
			echo "<!-- id_comment = $id_comment -->\n";
			echo '<input type="hidden" name="id_comment" value="' . $id_comment . '" />' . "\n";
		}

		echo '<p class="normal_text">';
		echo '<textarea name="comment" id="input_expense_comment" class="frm_tarea" rows="3" cols="60">'
			. clean_output($this->getDataString('comment'))
			. "</textarea>\n";
		echo "</p>\n";
	}
}

class LcmExpenseListUI {
	var $search;
	var $list_pos;
	var $number_of_rows;

	function LcmExpenseList() {
		$this->search = '';
		$this->list_pos = intval(_request('list_pos', 0));
		$this->number_of_rows = 0;
	}

	function setSearchTerm($term) {
		$this->search = $term;
	}

	function start() {
		$cpt = 0;
		$headers = array();

		$headers[$cpt]['title'] = '#'; // TRAD
		$headers[$cpt]['order'] = 'no_order';
		$cpt++;

		$headers[$cpt]['title'] = 'User'; // TRAD
		$headers[$cpt]['order'] = 'no_order';
		$cpt++;

		$headers[$cpt]['title'] = 'Case'; // TRAD
		$headers[$cpt]['order'] = 'no_order';
		$cpt++;

		$headers[$cpt]['title'] = _Th('time_input_date_creation');
		$headers[$cpt]['order'] = 'date_order';
		$headers[$cpt]['default'] = 'DESC';
		$cpt++;

		$headers[$cpt]['title'] = _Th('expense_input_type');
		$headers[$cpt]['order'] = 'type_order';
		$headers[$cpt]['default'] = 'DESC';
		$cpt++;

		$headers[$cpt]['title'] = _Th('expense_input_description');
		$headers[$cpt]['order'] = 'no_order';
		$headers[$cpt]['more'] = 'desc';
		$cpt++;

		$headers[$cpt]['title'] = "comments"; // TRAD
		$headers[$cpt]['order'] = 'no_order';
		$cpt++;

		$headers[$cpt]['title'] = _Th('time_input_date_updated');
		$headers[$cpt]['order'] = 'upddate_order';
		$headers[$cpt]['default'] = 'DESC';
		$cpt++;

		// XXX actually, it would be nice to be able to order..
		// but this would require us to put numbers in status names
		$headers[$cpt]['title'] = _Th('expense_input_status');
		$headers[$cpt]['order'] = 'no_order';
		$cpt++;

		show_list_start($headers);
	}

	function printList() {
		global $prefs;

		// Select cases of which the current user is author
		$q = "SELECT e.id_expense, e.id_case, e.id_author, e.status, e.type, 
				e.description, e.date_creation, e.date_update, e.pub_read,
				e.pub_write, a.name_first, a.name_middle, a.name_last,
				count(ec.id_expense) as nb_comments
			FROM lcm_expense as e, lcm_expense_comment as ec, lcm_author as a ";

		$q .= " WHERE (ec.id_expense = e.id_expense AND a.id_author = e.id_author ";

		if ($this->search) {
			$q .= " AND (";

			if (is_numeric($this->search))
				$q .= " e.id_expense = " . $this->search . " OR ";

			$q .= " e.description LIKE '%" . $this->search . "%' ";
			$q .= " )";
		}

		$q .= ")";

		//
		// Apply filters to SQL
		//

		// Case owner TODO
		// $q .= " AND " . $q_owner;

		// Period (date_creation) to show
		if ($prefs['case_period'] < 1900) // since X days
			// $q .= " AND TO_DAYS(NOW()) - TO_DAYS(date_creation) < " . $prefs['case_period'];
			$q .= " AND " . lcm_query_trunc_field('NOW()', 'day') . ' - '
				. lcm_query_trunc_field('e.date_creation', 'day') . ' < ' . $prefs['case_period'];
		else // for year X
			$q .= " AND " . lcm_query_trunc_field('e.date_creation', 'year') . ' = ' . $prefs['case_period'];

		// Sort cases by creation date TODO
		/*
		$case_order = 'DESC';
		if (isset($_REQUEST['case_order']))
			if ($_REQUEST['case_order'] == 'ASC' || $_REQUEST['case_order'] == 'DESC')
				$case_order = $_REQUEST['case_order'];

		$q .= " ORDER BY date_creation " . $case_order;
		*/

		$q .= " GROUP BY e.id_expense, e.id_case, e.id_author, e.status, e.type, e.description, e.date_creation, e.date_update, e.pub_read, e.pub_write, a.name_first, a.name_middle, a.name_last ";

		$result = lcm_query($q);

		// Check for correct start position of the list
		$this->number_of_rows = lcm_num_rows($result);

		if ($this->list_pos >= $this->number_of_rows)
			$this->list_pos = 0;

		// Position to the page info start
		if ($this->list_pos > 0)
			if (! lcm_data_seek($result, $this->list_pos))
				lcm_panic("Error seeking position " . $this->list_pos . " in the result");

		for ($i = 0; (($i<$prefs['page_rows']) && ($row = lcm_fetch_array($result))); $i++) {
			$css = ($i % 2 ? "dark" : "light");

			echo "<tr>\n";

			// Expense ID
			echo "<td class='tbl_cont_" . $css . "'>";
			echo highlight_matches($row['id_expense'], $this->search);
			echo "</td>\n";

			// Attached to case..
			echo "<td class='tbl_cont_" . $css . "'>";
			echo get_person_initials($row);
			echo "</td>\n";

			// Attached to case..
			echo "<td class='tbl_cont_" . $css . "'>";
			echo ($row['id_case'] ? $row['id_case'] : '');
			echo "</td>\n";

			// Date creation
			echo "<td class='tbl_cont_" . $css . "'>";
			echo format_date($row['date_creation'], 'short');
			echo "</td>\n";

			// Type
			echo "<td class='tbl_cont_" . $css . "'>";
			echo $row['type'];
			echo "</td>\n";

			// Description
			global $fu_desc_len; // configure via my_options.php with $GLOBALS['fu_desc_len'] = NNN;
			$more_desc = _request('more_desc', 0);
			$desc_length = ((isset($fu_desc_len) && $fu_desc_len > 0) ? $fu_desc_len : 256);
			$description = $row['description'];

			if ($more_desc || strlen(lcm_utf8_decode($row['description'])) < $desc_length) 
				$description = $row['description'];
			else
				$description = substr($row['description'], 0, $desc_length) . '...';

			echo "<td class='tbl_cont_" . $css . "'>";
			echo '<a class="content_link" href="exp_det.php?expense=' . $row['id_expense'] . '">';
			echo nl2br($description);
			echo "</a>";
			echo "</td>\n";

			// # Comments
			echo "<td class='tbl_cont_" . $css . "'>";
			echo $row['nb_comments'];
			echo "</td>\n";

			// Date update
			echo "<td class='tbl_cont_" . $css . "'>";

			if ($row['date_update'] != $row['date_creation'])
				echo format_date($row['date_update'], 'short');

			echo "</td>\n";

			// Status
			echo "<td class='tbl_cont_" . $css . "'>";
			echo $row['status'];
			echo "</td>\n";

			echo "</tr>\n";
		}

	}

	function finish() {
		show_listcase_end($this->list_pos, $this->number_of_rows);
	}
}

?>