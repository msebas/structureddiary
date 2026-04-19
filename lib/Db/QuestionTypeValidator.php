<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use InvalidArgumentException;

final class QuestionTypeValidator {
	/**
	 * @param list<string>|null $choices
	 */
	public static function validateQuestionDefinition(string $type, ?float $minimum, ?float $maximum, ?array $choices): void {
		if (!in_array($type, QuestionTypes::values(), true)) {
			throw new InvalidArgumentException('Unsupported question type.');
		}

		if ($type === QuestionTypes::SELECT && ($choices === null || $choices === [])) {
			throw new InvalidArgumentException('Selection questions require at least one choice.');
		}
		if (($type === QuestionTypes::SELECT || $type === QuestionTypes::EDITABLE_SELECT) && $choices !== null) {
			foreach ($choices as $choice) {
				if (trim($choice) === '') {
					throw new InvalidArgumentException('Selection choices cannot be empty.');
				}
			}
		}
		if ($type !== QuestionTypes::SELECT && $type !== QuestionTypes::EDITABLE_SELECT && $choices !== null) {
			throw new InvalidArgumentException('Choices are only valid for selection questions.');
		}

		if (in_array($type, [QuestionTypes::TEXT, QuestionTypes::NUMBER, QuestionTypes::INTEGER, QuestionTypes::RATING], true)
			&& $minimum !== null && $maximum !== null && $minimum > $maximum) {
			throw new InvalidArgumentException('Minimum cannot be greater than maximum.');
		}

		if ($type === QuestionTypes::RATING) {
			if (($minimum !== null && $minimum < 0) || ($maximum !== null && $maximum > 10)) {
				throw new InvalidArgumentException('Ratings must stay between 0 and 10.');
			}
		}
		if ($type === QuestionTypes::INTEGER) {
			if (($minimum !== null && floor($minimum) !== $minimum) || ($maximum !== null && floor($maximum) !== $maximum)) {
				throw new InvalidArgumentException('Integer questions must use whole-number minimum and maximum values.');
			}
		}
	}

	public static function validateAnswerPayload(Question $question, ?string $textContent, ?float $numericContent): void {
		switch ($question->getType()) {
			case QuestionTypes::TEXT:
				$value = $textContent ?? '';
				$length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
				if ($question->getMinimum() !== null && $length < $question->getMinimum()) {
					throw new InvalidArgumentException('Text answer is shorter than the configured minimum.');
				}
				if ($question->getMaximum() !== null && $length > $question->getMaximum()) {
					throw new InvalidArgumentException('Text answer is longer than the configured maximum.');
				}
				return;

			case QuestionTypes::BOOLEAN:
				if ($numericContent === null || !in_array($numericContent, [0.0, 1.0], true)) {
					throw new InvalidArgumentException('Boolean answers must use numeric_content 0 or 1.');
				}
				return;

			case QuestionTypes::RATING:
				if ($numericContent === null || $numericContent < 0 || $numericContent > 10) {
					throw new InvalidArgumentException('Ratings must be between 0 and 10.');
				}
				return;

			case QuestionTypes::NUMBER:
				if ($numericContent === null) {
					throw new InvalidArgumentException('Number answers require numeric_content.');
				}
				if ($question->getMinimum() !== null && $numericContent < $question->getMinimum()) {
					throw new InvalidArgumentException('Number answer is lower than the configured minimum.');
				}
				if ($question->getMaximum() !== null && $numericContent > $question->getMaximum()) {
					throw new InvalidArgumentException('Number answer is higher than the configured maximum.');
				}
				return;

			case QuestionTypes::INTEGER:
				if ($numericContent === null) {
					throw new InvalidArgumentException('Integer answers require numeric_content.');
				}
				if (floor($numericContent) !== $numericContent) {
					throw new InvalidArgumentException('Integer answers must use whole numbers.');
				}
				if ($question->getMinimum() !== null && $numericContent < $question->getMinimum()) {
					throw new InvalidArgumentException('Integer answer is lower than the configured minimum.');
				}
				if ($question->getMaximum() !== null && $numericContent > $question->getMaximum()) {
					throw new InvalidArgumentException('Integer answer is higher than the configured maximum.');
				}
				return;

			case QuestionTypes::TIME:
				if ($textContent === null || preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $textContent) !== 1) {
					throw new InvalidArgumentException('Time answers must use HH:MM or HH:MM:SS.');
				}
				$parts = array_map('intval', explode(':', $textContent));
				if ($parts[0] > 23 || $parts[1] > 59 || (count($parts) === 3 && $parts[2] > 59)) {
					throw new InvalidArgumentException('Time answers must use a valid 24-hour clock time.');
				}
				return;

			case QuestionTypes::SELECT:
				$choices = $question->getChoices() ?? [];
				if ($textContent === null || !in_array($textContent, $choices, true)) {
					throw new InvalidArgumentException('Selection answer must match one of the configured choices.');
				}
				return;

			case QuestionTypes::EDITABLE_SELECT:
				if ($textContent === null || $textContent === '') {
					throw new InvalidArgumentException('Editable selection answers require text_content.');
				}
				return;
		}
	}

	public static function answerIsValidForQuestion(Answer $answer, Question $question): bool {
		try {
			self::validateAnswerPayload($question, $answer->getTextContent(), $answer->getNumericContent());
			return true;
		} catch (InvalidArgumentException) {
			return false;
		}
	}

	private function __construct() {
	}
}
