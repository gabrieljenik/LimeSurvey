<?php

namespace ls\tests;

/**
 * @group mail
 */
class MailingTest extends TestBaseClass
{
    /** @var \DummyMailer */
    private static $plugin;

    /**
     * @inheritdoc
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Load plugin
        self::$plugin = self::loadTestPlugin('DummyMailer');

        // Set dummy controller so that LimeMailer doesn't fail while creating URLs
        \Yii::app()->setController(new DummyController('dummyid'));

        // Import survey
        self::importSurvey('tests/data/surveys/survey_archive_MailingTest.lsa');
    }

    public function testSendingOK()
    {
        $tid = 1;
        $token = \Token::model(self::$surveyId)->findByPk($tid);
        $this->assertNotEmpty($token);

        $results = emailTokens(self::$surveyId, [$token], 'invite');
        $this->assertArrayHasKey($tid, $results);
        $this->assertArrayHasKey('status', $results[$tid]);
        $this->assertEquals('OK', $results[$tid]['status']);
    }

    public function testSendingError()
    {
        $tid = 2;
        $expectedError = "Dummy error";
        self::$plugin->SetError($expectedError);
        $token = \Token::model(self::$surveyId)->findByPk($tid);
        $this->assertNotEmpty($token);

        $results = emailTokens(self::$surveyId, [$token], 'invite');
        $this->assertArrayHasKey($tid, $results);
        $this->assertArrayHasKey('status', $results[$tid]);
        $this->assertEquals('fail', $results[$tid]['status']);
        $this->assertArrayHasKey('error', $results[$tid]);
        $this->assertEquals($expectedError, $results[$tid]['error']);
    }
}
