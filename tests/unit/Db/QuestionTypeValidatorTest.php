<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\QuestionTypeValidator;
use OCA\StructuredDiary\Db\QuestionTypes;
use PHPUnit\Framework\TestCase;

final class QuestionTypeValidatorTest extends TestCase {
	public function testValidateQuestionDefinitionIgnoresMinimumAndMaximumForBoolean(): void {
		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::BOOLEAN, 10.0, 1.0, null);

		$this->addToAssertionCount(1);
	}

	public function testValidateQuestionDefinitionRejectsUnsupportedType(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported question type.');

		QuestionTypeValidator::validateQuestionDefinition('unsupported', null, null, null);
	}

	public function testValidateQuestionDefinitionRejectsSelectWithoutChoices(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Selection questions require at least one choice.');

		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::SELECT, null, null, null);
	}

	public function testValidateQuestionDefinitionRejectsChoicesForNonSelectionType(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Choices are only valid for selection questions.');

		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::TEXT, null, null, ['yes']);
	}

	public function testValidateQuestionDefinitionRejectsEmptySelectChoice(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Selection choices cannot be empty.');

		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::SELECT, null, null, ['yes', ' ']);
	}

	public function testValidateQuestionDefinitionAllowsChoicesForEditableSelect(): void {
		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::EDITABLE_SELECT, null, null, ['yes']);

		$this->addToAssertionCount(1);
	}

	public function testValidateQuestionDefinitionRejectsMinimumGreaterThanMaximumForNumericTypes(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Minimum cannot be greater than maximum.');

		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::NUMBER, 5.0, 1.0, null);
	}

	public function testValidateQuestionDefinitionRejectsRatingOutsideZeroToTen(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Ratings must stay between 0 and 10.');

		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::RATING, -1.0, 10.0, null);
	}

	public function testValidateQuestionDefinitionAcceptsRatingWithinZeroToTen(): void {
		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::RATING, 0.0, 10.0, null);

		$this->addToAssertionCount(1);
	}

	public function testValidateQuestionDefinitionRejectsFractionalMinimumForInteger(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Integer questions must use whole-number minimum and maximum values.');

		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::INTEGER, 1.5, 10.0, null);
	}

	public function testValidateQuestionDefinitionRejectsFractionalMaximumForInteger(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Integer questions must use whole-number minimum and maximum values.');

		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::INTEGER, 1.0, 10.5, null);
	}

	public function testValidateQuestionDefinitionAcceptsWholeNumberBoundsForInteger(): void {
		QuestionTypeValidator::validateQuestionDefinition(QuestionTypes::INTEGER, 1.0, 10.0, null);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadAcceptsWholeNumberForIntegerType(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::INTEGER);
		$question->setMinimum(1.0);
		$question->setMaximum(10.0);

		QuestionTypeValidator::validateAnswerPayload($question, null, 3.0);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadRejectsFractionForIntegerType(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::INTEGER);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Integer answers must use whole numbers.');

		QuestionTypeValidator::validateAnswerPayload($question, null, 3.5);
	}

	public function testValidateAnswerPayloadRejectsTextShorterThanMinimum(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TEXT);
		$question->setMinimum(5.0);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Text answer is shorter than the configured minimum.');

		QuestionTypeValidator::validateAnswerPayload($question, 'abc', null);
	}

	public function testValidateAnswerPayloadRejectsTextLongerThanMaximum(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TEXT);
		$question->setMaximum(2.0);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Text answer is longer than the configured maximum.');

		QuestionTypeValidator::validateAnswerPayload($question, 'abcd', null);
	}

	public function testValidateAnswerPayloadAcceptsTextWithinBounds(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TEXT);
		$question->setMinimum(2.0);
		$question->setMaximum(5.0);

		QuestionTypeValidator::validateAnswerPayload($question, 'abcd', null);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadRejectsBooleanWithoutNumericContent(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::BOOLEAN);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Boolean answers must use numeric_content 0 or 1.');

		QuestionTypeValidator::validateAnswerPayload($question, null, null);
	}

	public function testValidateAnswerPayloadAcceptsBooleanOne(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::BOOLEAN);

		QuestionTypeValidator::validateAnswerPayload($question, null, 1.0);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadRejectsRatingOutsideRange(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::RATING);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Ratings must be between 0 and 10.');

		QuestionTypeValidator::validateAnswerPayload($question, null, 11.0);
	}

	public function testValidateAnswerPayloadAcceptsRatingWithinRange(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::RATING);

		QuestionTypeValidator::validateAnswerPayload($question, null, 7.5);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadRejectsNumberWithoutNumericContent(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::NUMBER);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Number answers require numeric_content.');

		QuestionTypeValidator::validateAnswerPayload($question, null, null);
	}

	public function testValidateAnswerPayloadRejectsNumberBelowMinimum(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::NUMBER);
		$question->setMinimum(3.0);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Number answer is lower than the configured minimum.');

		QuestionTypeValidator::validateAnswerPayload($question, null, 2.0);
	}

	public function testValidateAnswerPayloadRejectsNumberAboveMaximum(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::NUMBER);
		$question->setMaximum(3.0);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Number answer is higher than the configured maximum.');

		QuestionTypeValidator::validateAnswerPayload($question, null, 4.0);
	}

	public function testValidateAnswerPayloadAcceptsNumberWithinBounds(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::NUMBER);
		$question->setMinimum(1.0);
		$question->setMaximum(5.0);

		QuestionTypeValidator::validateAnswerPayload($question, null, 3.5);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadRejectsIntegerWithoutNumericContent(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::INTEGER);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Integer answers require numeric_content.');

		QuestionTypeValidator::validateAnswerPayload($question, null, null);
	}

	public function testValidateAnswerPayloadRejectsIntegerBelowMinimum(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::INTEGER);
		$question->setMinimum(3.0);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Integer answer is lower than the configured minimum.');

		QuestionTypeValidator::validateAnswerPayload($question, null, 2.0);
	}

	public function testValidateAnswerPayloadRejectsIntegerAboveMaximum(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::INTEGER);
		$question->setMaximum(3.0);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Integer answer is higher than the configured maximum.');

		QuestionTypeValidator::validateAnswerPayload($question, null, 4.0);
	}

	public function testValidateAnswerPayloadRejectsInvalidTimeFormat(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TIME);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Time answers must use HH:MM or HH:MM:SS.');

		QuestionTypeValidator::validateAnswerPayload($question, '7:30', null);
	}

	public function testValidateAnswerPayloadAcceptsTimeWithSeconds(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TIME);

		QuestionTypeValidator::validateAnswerPayload($question, '07:30:15', null);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadAcceptsTimeWithoutSeconds(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TIME);

		QuestionTypeValidator::validateAnswerPayload($question, '07:30', null);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadRejectsHourOutside24HourClock(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TIME);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Time answers must use a valid 24-hour clock time.');

		QuestionTypeValidator::validateAnswerPayload($question, '24:00', null);
	}

	public function testValidateAnswerPayloadRejectsMinuteOutsideClockRange(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TIME);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Time answers must use a valid 24-hour clock time.');

		QuestionTypeValidator::validateAnswerPayload($question, '12:60', null);
	}

	public function testValidateAnswerPayloadRejectsSecondOutsideClockRange(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TIME);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Time answers must use a valid 24-hour clock time.');

		QuestionTypeValidator::validateAnswerPayload($question, '12:30:60', null);
	}

	public function testValidateAnswerPayloadRejectsSelectionOutsideChoices(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::SELECT);
		$question->setJsonChoices(json_encode(['yes', 'no'], JSON_THROW_ON_ERROR));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Selection answer must match one of the configured choices.');

		QuestionTypeValidator::validateAnswerPayload($question, 'maybe', null);
	}

	public function testValidateAnswerPayloadAcceptsSelectionWithinChoices(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::SELECT);
		$question->setJsonChoices(json_encode(['yes', 'no'], JSON_THROW_ON_ERROR));

		QuestionTypeValidator::validateAnswerPayload($question, 'yes', null);

		$this->addToAssertionCount(1);
	}

	public function testValidateAnswerPayloadRejectsEditableSelectionWithoutText(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::EDITABLE_SELECT);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Editable selection answers require text_content.');

		QuestionTypeValidator::validateAnswerPayload($question, '', null);
	}

	public function testValidateAnswerPayloadAcceptsEditableSelectionWithText(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::EDITABLE_SELECT);

		QuestionTypeValidator::validateAnswerPayload($question, 'custom', null);

		$this->addToAssertionCount(1);
	}

	public function testAnswerIsValidForQuestionReturnsTrueForValidPayload(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::TEXT);
		$answer = new \OCA\StructuredDiary\Db\Answer();
		$answer->setTextContent('valid');

		$this->assertTrue(QuestionTypeValidator::answerIsValidForQuestion($answer, $question));
	}

	public function testAnswerIsValidForQuestionReturnsFalseForInvalidPayload(): void {
		$question = new \OCA\StructuredDiary\Db\Question();
		$question->setType(QuestionTypes::NUMBER);
		$answer = new \OCA\StructuredDiary\Db\Answer();
		$answer->setTextContent('invalid');

		$this->assertFalse(QuestionTypeValidator::answerIsValidForQuestion($answer, $question));
	}
}
