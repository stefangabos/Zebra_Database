<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for transaction_start() and transaction_complete()
 *
 * These need a storage engine that can actually roll back, so they use a table of their own rather than
 * the shared fixtures - the test tables are InnoDB, but a table created here says so explicitly.
 */
class TransactionTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->db->query('DROP TABLE IF EXISTS test_transactions');
        $this->db->query('CREATE TABLE test_transactions (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(50)) ENGINE=InnoDB');
    }

    protected function tearDown(): void {
        if ($this->db) $this->db->query('DROP TABLE IF EXISTS test_transactions');
        parent::tearDown();
    }

    private function rows() {
        return (int)$this->db->dcount('*', 'test_transactions');
    }

    private function insert($label) {
        return $this->db->insert('test_transactions', ['label' => $label]);
    }

    // COMMITTING

    public function testACommittedTransactionKeepsItsChanges() {
        $this->assertTrue($this->db->transaction_start());

        $this->insert('one');
        $this->insert('two');

        $this->assertTrue($this->db->transaction_complete());
        $this->assertSame(2, $this->rows());
    }

    public function testAnEmptyTransactionCommitsCleanly() {
        $this->db->transaction_start();

        $this->assertTrue($this->db->transaction_complete());
        $this->assertSame(0, $this->rows());
    }

    public function testSeveralTransactionsCanRunOneAfterTheOther() {
        for ($round = 1; $round <= 3; $round++) {
            $this->assertTrue($this->db->transaction_start(), 'Round ' . $round);
            $this->insert('round ' . $round);
            $this->assertTrue($this->db->transaction_complete(), 'Round ' . $round);
        }

        $this->assertSame(3, $this->rows());
    }

    // ROLLING BACK ON ERROR

    public function testAFailingQueryRollsTheWholeTransactionBack() {
        $this->db->halt_on_errors = false;

        $this->db->transaction_start();

        $this->insert('before the failure');
        $this->db->query('INSERT INTO no_such_table (label) VALUES ("boom")');
        $this->insert('after the failure');

        $this->assertFalse($this->db->transaction_complete(), 'A transaction that had an error has failed');
        $this->assertSame(0, $this->rows(), 'None of the rows may survive');
    }

    /**
     * The rollback is decided by the transaction, not by where the failure happened to be
     */
    public function testAFailureAsTheLastQueryStillRollsBack() {
        $this->db->halt_on_errors = false;

        $this->db->transaction_start();
        $this->insert('one');
        $this->db->query('INSERT INTO no_such_table (label) VALUES ("boom")');

        $this->assertFalse($this->db->transaction_complete());
        $this->assertSame(0, $this->rows());
    }

    // TEST TRANSACTIONS

    /**
     * transaction_start(TRUE) starts a transaction that is always rolled back, so that a set of queries can
     * be tried out without keeping anything. Completing one is a success and has to be reported as such -
     * the status was reset to 0 before being compared against the value standing for a test transaction, so
     * the comparison could never be true and a test transaction was indistinguishable from a failed one.
     */
    public function testATestTransactionReportsSuccess() {
        $this->assertTrue($this->db->transaction_start(true));

        $this->insert('one');

        $this->assertTrue($this->db->transaction_complete(), 'A test transaction that ran fine is a success');
    }

    public function testATestTransactionKeepsNothing() {
        $this->db->transaction_start(true);

        $this->insert('one');
        $this->insert('two');
        $this->assertSame(2, $this->rows(), 'The rows are visible from inside the transaction');

        $this->db->transaction_complete();

        $this->assertSame(0, $this->rows(), 'But none of them are kept');
    }

    /**
     * A test transaction is deliberately never marked as failed - the query method skips that when a test
     * transaction is in progress, since it is going to be rolled back either way. So the return value says
     * that the test ran and kept nothing, not that every query in it worked. A failing query reports itself
     * at the point it fails.
     */
    public function testATestTransactionReportsSuccessEvenWithAFailingQuery() {
        $this->db->halt_on_errors = false;

        $this->db->transaction_start(true);
        $this->insert('one');

        $this->assertFalse(
            $this->db->query('INSERT INTO no_such_table (label) VALUES ("boom")'),
            'The query itself is where the failure shows'
        );
        $this->assertStringContainsString('no_such_table', $this->db->error());

        $this->assertTrue($this->db->transaction_complete());
        $this->assertSame(0, $this->rows());
    }

    public function testATestTransactionCanBeFollowedByARealOne() {
        $this->db->transaction_start(true);
        $this->insert('thrown away');
        $this->db->transaction_complete();

        $this->db->transaction_start();
        $this->insert('kept');

        $this->assertTrue($this->db->transaction_complete());
        $this->assertSame(1, $this->rows(), 'The test transaction must not have left the flag behind');
    }

    // MISUSE

    public function testStartingATransactionWhileOneIsInProgressFails() {
        $this->db->halt_on_errors = false;

        $this->assertTrue($this->db->transaction_start());
        $this->assertFalse($this->db->transaction_start(), 'Transactions cannot be nested');

        $this->db->transaction_complete();
    }

    public function testCompletingWithoutATransactionInProgressFails() {
        $this->db->halt_on_errors = false;

        $this->assertFalse($this->db->transaction_complete());
    }

    public function testTheFlagIsClearedAfterAFailedTransaction() {
        $this->db->halt_on_errors = false;

        $this->db->transaction_start();
        $this->db->query('INSERT INTO no_such_table (label) VALUES ("boom")');
        $this->db->transaction_complete();

        // if the flag had been left behind, this would be refused as a nested transaction
        $this->assertTrue($this->db->transaction_start(), 'A new transaction has to be possible afterwards');
        $this->insert('after recovery');

        $this->assertTrue($this->db->transaction_complete());
        $this->assertSame(1, $this->rows());
    }
}
