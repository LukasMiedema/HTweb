<?php
namespace Receipts;

class Model_Receipt extends \Orm\Model
{
	protected static $_properties = array(
		'id',
		'date' => array(
			'validation' => array('required', 'valid_date'),
		),
		'notes',
		'created_at',
		'updated_at',
	);

	protected static $_observers = array(
		'Orm\Observer_CreatedAt' => array(
			'events' => array('before_insert'),
			'mysql_timestamp' => false,
		),
		'Orm\Observer_UpdatedAt' => array(
			'events' => array('before_update'),
			'mysql_timestamp' => false,
		),
	);

	protected static $_table_name = 'receipts';	
	
	protected static $_has_many = array(
		'sessions' => array(
			'model_to' => '\Receipts\Model_Session_Receipt',
			'cascade_delete' => false,
		),
		'products' => array(
			'model_to' => '\Receipts\Model_Product_Receipt',
			'cascade_delete' => false,
		),
		'users' => array(
			'model_to' => '\Receipts\Model_User_Receipt',
			'cascade_delete' => false,
		),
	);
	
	/**
	 * Retrieve all receipts related to the given user
	 * @param int $user_id
	 * @return array \Receipts\Model_Receipt
	 */
	public static function get_by_user($user_id) {
		return Model_Receipt::query()->related('users', array(
			'where' => array(
				array('user_id', $user_id),
			)
		))->get();
	}
	
	/**
	 * Retrieve a list of user receipts in this receipt sorted by name alphabetical
	 * @return array \Receipts\Model_User_Receipt
	 */
	public function get_users_sorted() {
		return Model_User_Receipt::query()
			->related('user')
			->order_by('user.name', 'asc')
			->where('receipt_id', $this->id)
			->get();
	}
	
	/**
	 * Calculate points checksum. Should be 0.
	 * @return type
	 */
	public function validate_points() {
		return array_values(\DB::select(\DB::expr('SUM(points)'))
			->from('user_receipts')
			->where('receipt_id', $this->id)
			->execute()[0])[0];
	}
	
	/**
	 * Calculate balance checksum. Should be 0.
	 * @return type
	 */
	public function validate_balance() {
		return array_values(\DB::select(\DB::expr('SUM(balance)'))
			->from('user_receipts')
			->where('receipt_id', $this->id)
			->execute()[0])[0];
	}
	
	/**
	 * Retrieve a list of all people with a positive balance sorted highest credit first
	 * @return [Model_User_Receipt]
	 */
	public function get_creditors() {
		return Model_User_Receipt::find('all', array(
				'order_by' => array('balance' => 'desc'),
				'where' => array(
				array('receipt_id', $this->id),
				array('balance', '>', 0)
			),
		));
	}
	
	/**
	 * Retrieve a list of all people with a negative balance sorted highest debt first
	 * @return [Model_User_Receipt]
	 */
	public function get_debtors() {
		return Model_User_Receipt::find('all', array(
				'order_by' => array('balance' => 'asc'),
				'where' => array(
				array('receipt_id', $this->id),
				array('balance', '<', 0)
			),
		));
	}
	
	/**
	 * Get a list of lists (from user_id, to user_id, amount) describing transactions.
	 * Warning: This changes the balance model value for debtors.
	 * @return array
	 */
	public function get_transaction_schema() {
		$creditors = $this->get_creditors();
		$debtors = $this->get_debtors();
		
		$result = [];
		
		foreach($creditors as $creditor) {
			$credit = $creditor->balance;
			
			foreach($debtors as $debtor) {
				$debit = $debtor->balance;
				$overshot = $credit + $debit;
				
				if($overshot <= 0) {
					// Debt is larger than credit
					$debtor->balance = $overshot;
					array_push($result, [$debtor->user->id, $creditor->user->id, $credit]);
					break; 
				} else {
					// Dept is smaller than credit
					$debtor->balance = 0;
					$credit = $overshot;
					array_push($result, [$debtor->user->id, $creditor->user->id, $debit]);
				}
			}
		}
		return $result;
	}
}
