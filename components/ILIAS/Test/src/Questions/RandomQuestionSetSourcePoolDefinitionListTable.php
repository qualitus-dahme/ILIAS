<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Test\Questions;

use ILIAS\Data\Factory as DataFactory;
use ILIAS\Data\URI;
use ILIAS\Test\Utilities\TitleColumnsBuilder;
use ILIAS\Test\RequestDataCollector;
use ILIAS\UI\Component\Table\OrderingBinding;
use ILIAS\UI\Component\Table\OrderingRowBuilder;
use ILIAS\UI\Component\Table\Ordering as OrderingTable;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use ilTestRandomQuestionSetSourcePoolDefinitionList as PoolDefinitionList;
use ilTestRandomQuestionSetConfigGUI as ConfigGUI;
use Psr\Http\Message\ServerRequestInterface;

class RandomQuestionSetSourcePoolDefinitionListTable implements OrderingBinding
{
    protected URI $target;
    protected URLBuilder $url_builder;
    protected URLBuilderToken $id_token;

    public function __construct(
        protected readonly \ilCtrlInterface $ctrl,
        protected readonly \ilLanguage $lng,
        protected readonly UIFactory $ui_factory,
        protected readonly DataFactory $data_factory,
        protected readonly ServerRequestInterface $request,
        protected readonly TitleColumnsBuilder $title_builder,
        protected readonly \ilTestQuestionFilterLabelTranslator $taxonomy_translator,
        protected readonly PoolDefinitionList $source_pool_definition_list,
        protected readonly bool $editable,
        protected readonly bool $show_amount,
        protected readonly bool $show_mapped_taxonomy_filter
    ) {
        $this->target = $this->data_factory->uri((string) $this->request->getUri());
        $this->url_builder = new URLBuilder($this->target);
        [$this->url_builder, $this->id_token] = $this->url_builder->acquireParameters(
            ['src_pool_def'],
            'id'
        );
    }

    public function getRows(OrderingRowBuilder $row_builder, array $visible_column_ids): \Generator
    {
        foreach ($this->getData() as $qp) {
            $record = [
                'sequence_position' => (int) $qp['sequence_position'],
                'source_pool_label' => $this->title_builder->buildAccessCheckedQuestionpoolTitleAsLink(
                    $qp['ref_id'],
                    $qp['source_pool_label'],
                    true
                ),
                'taxonomy_filter' => $this->taxonomy_translator->getTaxonomyFilterLabel(
                    $qp['taxonomy_filter'],
                    '<br />'
                ),
                'lifecycle_filter' => $this->taxonomy_translator->getLifecycleFilterLabel($qp['lifecycle_filter']),
                'type_filter' => $this->taxonomy_translator->getTypeFilterLabel($qp['type_filter']),
                'question_amount' => (int) $qp['question_amount']
            ];
            yield $row_builder->buildOrderingRow((string) $qp['def_id'], $record);
        }
    }

    protected function getData(): array
    {
        $data = [];

        foreach ($this->source_pool_definition_list as $source_pool_definition) {
            $set = [];

            $set['def_id'] = $source_pool_definition->getId();
            $set['sequence_position'] = $source_pool_definition->getSequencePosition();
            $set['source_pool_label'] = $source_pool_definition->getPoolTitle();
            // fau: taxFilter/typeFilter - get the type and taxonomy filter for display
            if ($this->show_mapped_taxonomy_filter) {
                // mapped filter will be used after synchronisation
                $set['taxonomy_filter'] = $source_pool_definition->getMappedTaxonomyFilter();
            } else {
                // original filter will be used before synchronisation
                $set['taxonomy_filter'] = $source_pool_definition->getOriginalTaxonomyFilter();
            }
            $set['lifecycle_filter'] = $source_pool_definition->getLifecycleFilter();
            $set['type_filter'] = $source_pool_definition->getTypeFilter();
            // fau.
            $set['question_amount'] = $source_pool_definition->getQuestionAmount();
            $set['ref_id'] = $source_pool_definition->getPoolRefId();
            $data[] = $set;
        }

        usort($data, fn($a, $b) => $a['sequence_position'] <=> $b['sequence_position']);
        return $data;
    }

    public function getComponent(): OrderingTable
    {
        return $this->ui_factory->table()
            ->ordering(
                $this->lng->txt('tst_src_quest_pool_def_list_table'),
                $this->getColumns(),
                $this,
                $this->getTarget(ConfigGUI::CMD_SAVE_SRC_POOL_DEF_LIST)
            )
            ->withActions($this->getActions())
            ->withRequest($this->request)
            ->withOrderingDisabled(!$this->editable)
            ->withId('src_pool_def_list');
    }

    public function applySubmit(RequestDataCollector $request): void
    {
        $quest_pos = array_flip($this->getComponent()->getData());

        foreach ($this->source_pool_definition_list as $source_pool_definition) {
            $pool_id = $source_pool_definition->getId();
            $sequence_pos = array_key_exists($pool_id, $quest_pos) ? $quest_pos[$pool_id] : 0;
            $source_pool_definition->setSequencePosition($sequence_pos);
        }
    }

    /**
     * @return array<string, Column>
     */
    protected function getColumns(): array
    {
        $column_factory = $this->ui_factory->table()->column();
        $columns_definition = [
            'sequence_position' => $column_factory->number($this->lng->txt('position'))->withUnit('.'),
            'source_pool_label' => $column_factory->link($this->lng->txt('tst_source_question_pool')),
            'taxonomy_filter' => $column_factory->text(
                $this->lng->txt('tst_filter_taxonomy') . ' / ' . $this->lng->txt('tst_filter_tax_node')
            ),
            'lifecycle_filter' => $column_factory->text($this->lng->txt('qst_lifecycle')),
            'type_filter' => $column_factory->text($this->lng->txt('tst_filter_question_type')),
            'question_amount' => $column_factory->number($this->lng->txt('tst_question_amount')),
        ];

        $columns_conditions = [
            'sequence_position' => !$this->editable,
            'question_amount' => $this->show_amount,
        ];

        return array_filter($columns_definition, function ($key) use ($columns_conditions) {
            return !isset($columns_conditions[$key]) || $columns_conditions[$key];
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return array<string, Action>
     */
    protected function getActions(): array
    {
        if (!$this->editable) {
            return [];
        }
        return [
            'delete' => $this->ui_factory->table()->action()->standard(
                $this->lng->txt('delete'),
                $this->url_builder->withURI($this->getTarget(ConfigGUI::CMD_DELETE_SRC_POOL_DEF)),
                $this->id_token
            ),
            'edit' => $this->ui_factory->table()->action()->single(
                $this->lng->txt('edit'),
                $this->url_builder->withURI($this->getTarget(ConfigGUI::CMD_SHOW_EDIT_SRC_POOL_DEF_FORM)),
                $this->id_token
            )
        ];
    }

    protected function getTarget(string $cmd): URI
    {
        return $this->target->withParameter('cmd', $cmd);
    }
}
