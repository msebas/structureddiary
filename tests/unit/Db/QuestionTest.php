<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\Question;
use PHPUnit\Framework\TestCase;

final class QuestionTest extends TestCase {
	public function testJsonSerializeReturnsExpectedPayload(): void {
		$question = new Question();
		$question->setId(11);
		$question->setChainId(11);
		$question->setDiaryId(42);
		$question->setDiaryQuestionOrder(7);
		$question->setCreatedAt(1713254400);
		$question->setLabel('Mood');
		$question->setDisplayText('How is your mood?');
		$question->setType(Question::TYPE_SELECT);
		$question->setMinimum(1.0);
		$question->setMaximum(10.0);
		$question->setJsonChoices(json_encode(['good', 'bad'], JSON_THROW_ON_ERROR));
		$question->setActive(true);
		$question->setTemplateText('Pick the closest option.');
		$question->setPreviousVersionId(3);
		$question->setNextVersionId(12);

		$this->assertSame([
			'id' => 11,
			'chain_id' => 11,
			'diary_id' => 42,
			'diary_question_order' => 7,
			'created_at' => 1713254400,
			'label' => 'Mood',
			'display_text' => 'How is your mood?',
			'type' => Question::TYPE_SELECT,
			'minimum' => 1.0,
			'maximum' => 10.0,
			'choices' => ['good', 'bad'],
			'active' => true,
			'template_text' => 'Pick the closest option.',
			'previous_version_id' => 3,
			'next_version_id' => 12,
		], $question->jsonSerialize());
	}

	public function testGetChoicesReturnsNullForEmptyStorageAndNormalizesDecodedValues(): void {
		$question = new Question();
		$question->setJsonChoices('');
		$this->assertNull($question->getChoices());

		$question->setJsonChoices(json_encode(['1', 2, true], JSON_THROW_ON_ERROR));
		$this->assertSame(['1', '2', '1'], $question->getChoices());
	}

	public function testGetChoicesThrowsOnInvalidJson(): void {
		$question = new Question();
		$question->setJsonChoices('{');

		$this->expectException(\JsonException::class);
		$question->getChoices();
	}

	public function testJsonSerializeKeepsNullChoicesAndVersions(): void {
		$question = new Question();
		$question->setId(11);
		$question->setChainId(19);
		$question->setDiaryId(42);
		$question->setDiaryQuestionOrder(3);
		$question->setCreatedAt(1713254400);
		$question->setLabel('Mood');
		$question->setDisplayText('Mood');
		$question->setType(Question::TYPE_TEXT);
		$question->setMinimum(null);
		$question->setMaximum(null);
		$question->setJsonChoices(null);
		$question->setActive(false);
		$question->setTemplateText('');
		$question->setPreviousVersionId(null);
		$question->setNextVersionId(null);

		$this->assertSame([
			'id' => 11,
			'chain_id' => 19,
			'diary_id' => 42,
			'diary_question_order' => 3,
			'created_at' => 1713254400,
			'label' => 'Mood',
			'display_text' => 'Mood',
			'type' => Question::TYPE_TEXT,
			'minimum' => null,
			'maximum' => null,
			'choices' => null,
			'active' => false,
			'template_text' => '',
			'previous_version_id' => null,
			'next_version_id' => null,
		], $question->jsonSerialize());
	}
}
