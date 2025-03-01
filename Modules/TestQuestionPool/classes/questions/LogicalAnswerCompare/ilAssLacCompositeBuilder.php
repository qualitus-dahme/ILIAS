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

/**
 * Class CompositeBuilder
 *
 * Date: 27.03.13
 * Time: 12:18
 * @author Thomas Joußen <tjoussen@databay.de>
 */
class ilAssLacCompositeBuilder
{
    /**
     * This array defines the weights and direction of operators.<br />
     * It is required to build the composite tree with the correct depth structure
     *
     * @var array
     */
    protected $operators = array('<=','<','=','>=','>','<>','&','|');

    /**
     * Creates a composite tree structure from a nodes tree.
     * <p>
     * May fail on malformed input, e.g. because not all operators could be
     * handled. So ensure only to call this function with well-formed input data
     * or be prepared to handle type exceptions.
     *
     * @param array $nodes  an array structure of parsed nodes as returned by
     *         ilAssLacConditionParser#createNodeArray(), with type 'group'.
     *
     * @return ilAssLacAbstractComposite
     *
     * @throws ilAssLacCompositeBuilderException in some cases of invalid input.
     *
     * @see ilAssLacConditionParser#createNodeArray() for details on the
     * expected input structure.
     */
    public function create(array $nodes): ilAssLacAbstractComposite
    {
        if ($nodes['type'] == 'group') {
            foreach ($nodes['nodes'] as $key => $child) {
                if ($child['type'] == 'group') {
                    $nodes['nodes'][$key] = $this->create($child);
                }
            }

            foreach ($this->operators as $next_operator) {
                do {
                    $index = -1;
                    for ($i = 0; $i < count($nodes['nodes']); $i++) {
                        if (!is_object($nodes['nodes'][$i]) && $nodes['nodes'][$i]['type'] == 'operator' && $nodes['nodes'][$i]['value'] == $next_operator) {
                            $index = $i;
                            break;
                        }
                    }
                    if ($index >= 0) {
                        $operation_manufacture = ilAssLacOperationManufacturer::_getInstance();
                        $operator = $operation_manufacture->manufacture($nodes['nodes'][$index]['value']);

                        $operator->setNegated($nodes["negated"]);
                        $operator->addNode($this->getExpression($nodes, $index - 1));
                        $operator->addNode($this->getExpression($nodes, $index + 1));

                        $new_nodes = array_slice($nodes['nodes'], 0, $index - 1);
                        $new_nodes[] = $operator;
                        $nodes['nodes'] = array_merge($new_nodes, array_slice($nodes['nodes'], $index + 2));
                    }
                } while ($index >= 0);
            }
            return $nodes['nodes'][0];
        }
        throw new ilAssLacCompositeBuilderException(
            'need node structure with type group as input'
        );
    }

    /**
     * Manufacure an expression from the delivered node and the index. If an expression already exist in the node for<br />
     * for the delivered index, this function will return the existing expression
     *
     * @param array $node
     * @param int $index
     *
     * @return ilAssLacCompositeInterface
     */
    private function getExpression(array $node, $index)
    {
        $manufacturer = ilAssLacExpressionManufacturer::_getInstance();

        $expression = $node['nodes'][$index];
        if (!($expression instanceof ilAssLacAbstractComposite)) {
            $expression = $manufacturer->manufacture($node['nodes'][$index]['value']);
        }
        return $expression;
    }
}
