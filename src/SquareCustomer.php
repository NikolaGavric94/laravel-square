<?php

namespace Nikolag\Square;

use Nikolag\Square\Contracts\SquareContract;
use Nikolag\Square\Facades\Square;
use SquareConnect\Model\CreateCustomerRequest;

class SquareCustomer implements SquareContract {
    /**
     * @var int
     */
    private $id;
    /**
     * @var int
     */
    private $squareId;
	/**
	 * @var string
	 */
	private $firstName;
	/**
	 * @var string
	 */
	private $lastName;
	/**
	 * @var string
	 */
	private $companyName;
	/**
	 * @var string
	 */
	private $nickname;
	/**
	 * @var string
	 */
	private $email;
	/**
	 * @var string
	 */
	private $phone;
	/**
	 * @var int
	 */
	private $reference_id;
	/**
	 * @var string
	 */
	private $note;
    /**
     * @var CreateCustomerRequest
     */
    private $squareCustomerRequest;
    /**
     * @var array
     */
    private $cards;
    /**
     * @var LocationsApi
     */
    private $locationsAPI;
    /**
     * @var CustomersApi
     */
    private $customersAPI;
    /**
     * @var TransactionsApi
     */
    private $transactionsAPI;

	function __construct($data = null)
    {
        if($data) {
    		$this->setFirstName($data['firstName']);
    		$this->setLastName($data['lastName']);
    		$this->setCompanyName($data['companyName']);
    		$this->setNickname($data['nickname']);
    		$this->setEmail($data['email']);
    		$this->setPhone($data['phone']);
    		$this->setReferenceId($data['reference_id']);
    		$this->setNote($data['note']);
        }
        $this->locationsAPI = Square::locationsAPI();
        $this->customersAPI = Square::customersAPI();
        $this->transactionsAPI = Square::transactionsAPI();
	}

    /**
     * Gets the value of id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the value of id.
     *
     * @param int $id the id
     *
     * @return self
     */
    private function _setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets the value of squareId.
     *
     * @return int
     */
    public function getSquareId()
    {
        return $this->squareId;
    }

    /**
     * Sets the value of squareId.
     *
     * @param int $squareId the square id
     *
     * @return self
     */
    private function _setSquareId($squareId)
    {
        $this->squareId = $squareId;

        return $this;
    }

    /**
     * Gets the value of firstName.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Sets the value of firstName.
     *
     * @param string $firstName the first name
     *
     * @return self
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $fistName;

        return $this;
    }

    /**
     * Gets the value of lastName.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Sets the value of lastName.
     *
     * @param string $lastName the last name
     *
     * @return self
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * Gets the value of companyName.
     *
     * @return string
     */
    public function getCompanyName()
    {
        return $this->companyName;
    }

    /**
     * Sets the value of companyName.
     *
     * @param string $companyName the company name
     *
     * @return self
     */
    public function setCompanyName($companyName)
    {
        $this->companyName = $companyName;

        return $this;
    }

    /**
     * Gets the value of nickname.
     *
     * @return string
     */
    public function getNickname()
    {
        return $this->nickname;
    }

    /**
     * Sets the value of nickname.
     *
     * @param string $nickname the nickname
     *
     * @return self
     */
    public function setNickname($nickname)
    {
        $this->nickname = $nickname;

        return $this;
    }

    /**
     * Gets the value of email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Sets the value of email.
     *
     * @param string $email the email
     *
     * @return self
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Gets the value of phone.
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Sets the value of phone.
     *
     * @param string $phone the phone
     *
     * @return self
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Gets the value of reference_id.
     *
     * @return int
     */
    public function getReferenceId()
    {
        return $this->reference_id;
    }

    /**
     * Sets the value of reference_id.
     *
     * @param int $reference_id the reference id
     *
     * @return self
     */
    public function setReferenceId($reference_id)
    {
        $this->reference_id = $reference_id;

        return $this;
    }

    /**
     * Gets the value of note.
     *
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * Sets the value of note.
     *
     * @param string $note the note
     *
     * @return self
     */
    public function setNote($note)
    {
        $this->note = $note;

        return $this;
    }

    /**
     * Gets the value of squareCustomerRequest.
     *
     * @return CreateCustomerRequest
     */
    public function getSquareCustomerRequest()
    {
        return $this->squareCustomerRequest;
    }

    /**
     * Sets the value of squareCustomerRequest.
     *
     * @param CreateCustomerRequest $squareCustomerRequest the square customer request
     *
     * @return self
     */
    private function _setSquareCustomerRequest(CreateCustomerRequest $squareCustomerRequest)
    {
        $this->squareCustomerRequest = $squareCustomerRequest;

        return $this;
    }

    /**
     * Gets the value of cards.
     *
     * @return SquareCard[]
     */
    public function getCards()
    {
        return $this->cards;
    }

    /**
     * Sets the value of cards.
     *
     * @param SquareCard[] $cards the cards
     *
     * @return self
     */
    private function _setCards(array $cards)
    {
        $this->cards = $cards;

        return $this;
    }

    /**
     *
     */
    private function buildCustomerRequest()
    {
        $data = array(
            'given_name' => $this->getFirstName(),
            'family_name' => $this->getLastName(),
            'company_name' => $this->getCompanyName(),
            'nickname' => $this->getNickname(),
            'email_address' => $this->getEmail(),
            'phone_number' => $this->getPhone(),
            'reference_id' => $this->getReferenceId(),
            'note' => $this->getNote()
        );
        $customer = new CreateCustomerRequest($data);
        $this->setSquareCustomerRequest($customer);
    }

    /**
     *
     */
    function locations()
    {
        return $this->locationsAPI->listLocations();
    }

    /**
     *
     */
    function save()
    {
        if(!$this->squareId) {
            $this->customersAPI->createCustomer($this->getSquareCustomerRequest());
        } else {
            $this->customersAPI->updateCustomer($this->squareId, $this->getSquareCustomerRequest());
        }
    }

    /**
     *
     */
    function charge(float $amount, string $cardNonce) {
        $transaction = $this->transactionsAPI->charge($this->config->location_id, array(
            'idempotency_key' => uniqid(),
              'amount_money' => array(
                'amount' => $amount,
                'currency' => 'USD'
              ),
              'card_nonce' => $cardNonce,
        ));
        return $transaction;
    }
}