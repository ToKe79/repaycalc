<?php

$limit = 2500; // loan amount
$nr_of_payments = 12; // number of payments for APR calculation
$int_rate = 0.099; // interest rate
$stmt_day = 15; // day of month on which statements are issued
$due_days = 23; // number of days for payment term

$days2seconds = 60 * 60 * 24;

$now = time(); // current time
$loan_day = (int)date("j", $now); // current day without leading zero
$loan_month = (int)date("n", $now); // current month without leading zero
$loan_month = (int)date("n", $now); // current month without leading zero
$loan_year = (int)date("Y", $now); // current year - 4 digits
$loan_date = mktime(0, 0, 0, $loan_month, $loan_day, $loan_year); // date when loan is provided

if($loan_day >= $stmt_day) {
	// loan is provided on/after statement day, so first statement is in next month
	$frst_stmt_month = (int)date("n", strtotime("+1 month", $loan_date));
	$frst_stmt_year = (int)date("Y", strtotime("+1 month", $loan_date));
} else {
	// loan is provided before statement day, so first statement is in the same month
	$first_stmt_month = $loan_month;
	$first_stmt_year = $loan_year;
}

$frst_stmt_date = mktime(0, 0, 0, $frst_stmt_month, $stmt_day, $frst_stmt_year);

$finished = 0;
$repayment = round($limit/$nr_of_payments, 2);
$iter = 0;

while($finished == 0) {
	$iter++;
	$cur_stmt_date = $frst_stmt_date; // start with the first statement
	$cur_due_date = $cur_stmt_date + ($due_days * $days2seconds);
	$payments = array();
	$remainder = $limit;
	$broken = 0;
	echo "Iteration nr. ".$iter.": repayment amount = ".$repayment.": ";
	for($i = 1; $i <= $nr_of_payments; $i++) {
		if($i == 1) {
			// this is the first payment, no interest is applied
			$remainder = round($remainder - $repayment, 2);
			if($remainder < 0) {
				$broken = 1;
				break;;
			}
			$payments[$i]['on_interest'] = 0;
			$payments[$i]['on_principal'] = $repayment;
			$payments[$i]['remainder'] = $remainder;
			$payments[$i]['date'] = $cur_due_date;
			$payments[$i]['amouont'] = $repayment;
			$prev_stmt_date = $cur_stmt_date;
			$prev_due_date = $cur_due_date;
			$cur_stmt_date = (int)strtotime("+1 month", $cur_stmt_date);
			$cur_due_date = $cur_stmt_date + ($due_days * 24 * 60 * 60);
		} elseif($i == 2) {
			// with second payment only interest from previous due date until current statement date are applied
			$days = (int)round(($cur_stmt_date - $prev_due_date) / $days2seconds, 0);
			$interest = round($remainder * $int_rate / 360 * $days, 2);
			if($interest >= $repayment) {
				die("ERROR: calculated interest is higher than the repayment amount!!!\n");
			}
			$on_principal = $repayment - $interest;
			$prev_remainder = $remainder;
			$remainder = round($remainder - $on_principal, 2);
			if($remainder < 0) {
				$broken = 1;
				break;;
			}
			$payments[$i]['on_interest'] = $interest;
			$payments[$i]['on_principal'] = $on_principal;
			$payments[$i]['remainder'] = $remainder;
			$payments[$i]['date'] = $cur_due_date;
			$payments[$i]['amount'] = $repayment;
			$prev_stmt_date = $cur_stmt_date;
			$prev_due_date = $cur_due_date;
			$cur_stmt_date = (int)strtotime("+1 month", $cur_stmt_date);
			$cur_due_date = $cur_stmt_date + ($due_days * 24 * 60 * 60);
		} else {
			// on following statements interest from previous statement until previous due date and from previous due date until current statement date are applied
			$days = (int)round(($prev_due_date - $prev_stmt_date) / $days2seconds, 0);
			$interest = round($prev_remainder * $int_rate / 360 * $days, 2);
			$days = (int)round(($cur_stmt_date - $prev_due_date) / $days2seconds, 0);
			$interest+= round($remainder * $int_rate / 360 * $days, 2);
			if($interest >= $repayment) {
				die("ERROR: calculated interest is higher than the repayment amount!!!\n");
			}
			$on_principal = $repayment - $interest;
			$prev_remainder = $remainder;
			$remainder = round($remainder - $on_principal, 2);
			if($remainder < 0 && $i < 12) {
				$broken = 1;
				break;;
			}
			$payments[$i]['on_interest'] = $interest;
			$payments[$i]['on_principal'] = $on_principal;
			$payments[$i]['remainder'] = $remainder;
			$payments[$i]['date'] = $cur_due_date;
			$payments[$i]['amount'] = $repayment;
			$prev_stmt_date = $cur_stmt_date;
			$prev_due_date = $cur_due_date;
			$cur_stmt_date = (int)strtotime("+1 month", $cur_stmt_date);
			$cur_due_date = $cur_stmt_date + ($due_days * 24 * 60 * 60);
		}
	}

	if($broken) {
		echo "failed :-(\n";
		continue;;
	}

	if(abs($remainder) < 0.12) {
		echo "success ;]\n";
		$finished = 1;
		$payments[12]['on_principal']+= $remainder;
		$payments[12]['amount']+= $remainder;
		$payments[12]['remainder']-= $remainder;
	} else {
		echo "failed :-(\n";
		$repayment = $repayment + round($remainder/12, 2);
	}
}

?>
