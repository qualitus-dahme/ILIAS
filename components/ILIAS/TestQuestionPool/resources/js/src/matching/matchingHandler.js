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
 */

/**
 * @type {String}
 */
const matchingTypeOneToOne = '1:1';

/**
 * @type {String}
 */
const matchingTypeManyToMany = 'n:n';

/**
 * @type {String}
 */
const sourceAreaId = 'sourceArea';

/**
 * @type {String}
 */
const targetAreasClass = 'ilMatchingQuestionTerm';

/**
 * @type {String}
 */
const definitionElementClass = 'c-test__definition';

/**
 * @type {String}
 */
const termElementClass = 'c-test__term';

/**
 * @type {String}
 */
const placeholderClass = 'c-test__dropzone';

/**
 * @type {String}
 */
let matchingType;

/**
 * @type {DOMElement}
 */
let parentElement;

/**
 * @type {DOMElement}
 */
let placeholderElement;

function setup() {
  const answers = parentElement.querySelectorAll(`.${termElementClass}`);
  let elementHeight = 0;
  answers.forEach(
    (elem) => {
      if (elem.offsetHeight < elementHeight) {
        elementHeight = elem.offsetHeight;
      }
    },
  );
  parentElement.querySelectorAll(`.${placeholderClass}`).forEach(
    (elem) => {
      elem.style.height = `${answers.item(0).offsetHeight}px`;
    },
  );
  placeholderElement = parentElement.querySelector(`.${placeholderClass}`);
}

function updatePlaceholderElementsOneToOne(targetArea) {
  const firstChild = targetArea.firstElementChild;
  if (firstChild === null) {
    targetArea.prepend(placeholderElement.cloneNode());
    return;
  }

  if (firstChild.classList.contains(termElementClass) && firstChild.nextElementSibling !== null) {
    firstChild.nextElementSibling.remove();
  }
}

function updatePlaceholderElementsManyToMany(targetArea) {
  if (targetArea.firstElementChild === null
    || !targetArea.lastElementChild.classList.contains(placeholderClass)) {
    targetArea.append(placeholderElement.cloneNode());
  }
}

function updateAnswerElementsManyToMany(droppedElement, target, draggedElement) {
  if (draggedElement.parentNode.classList.contains(targetAreasClass)) {
    draggedElement.remove();
  }
  if (target.parentNode.id === sourceAreaId) {
    droppedElement.remove();
  }
}

function updateTerms(droppedElement, target, draggedElement) {
  if (matchingType === matchingTypeManyToMany) {
    updateAnswerElementsManyToMany(droppedElement, target, draggedElement);
  }
}

function updatePlaceholders() {
  parentElement.querySelectorAll(`.${targetAreasClass}`).forEach(
    (elem) => {
      if (matchingType === matchingTypeOneToOne) {
        updatePlaceholderElementsOneToOne(elem);
        return;
      }
      updatePlaceholderElementsManyToMany(elem);
    },
  );
}

function updateValues(droppedElement, target, source) {
  const dropData = droppedElement.dataset;
  if (source.id !== sourceAreaId) {
    const parentDefinitionInput = source.closest(`.${definitionElementClass}`).querySelector('input');
    const value = JSON.parse(parentDefinitionInput.value);
    const index = value.indexOf(dropData.id);
    if (index > -1) {
      value.splice(index, 1);
    }
    parentDefinitionInput.value = JSON.stringify(value);
    return;
  }
  const parentDefinition = target.closest(`.${definitionElementClass}`);
  const value = JSON.parse(parentDefinition.querySelector('input').value);
  value.push(dropData.id);
  parentDefinition.querySelector('input').value = JSON.stringify(value);
}

function changeHandler(droppedElement, target, draggedElement, source) {
  updateValues(droppedElement, target, source);
  updateTerms(droppedElement, target, draggedElement);
}

function onStartPrepareHandler(draggedElement) {
  updatePlaceholders();
  const sourceArea = parentElement.querySelector(`#${sourceAreaId}`);
  if (sourceArea.firstElementChild === null
    || !sourceArea.firstElementChild.classList.contains(placeholderClass)) {
    sourceArea.prepend(placeholderElement.cloneNode());
  }

  draggedElement.parentNode.querySelectorAll(`.${placeholderClass}`).forEach(
    (elem) => { elem.remove(); },
  );

  if (matchingType === matchingTypeManyToMany) {
    parentElement.querySelectorAll(`.${targetAreasClass}`).forEach(
      (elem) => {
        if (elem.lastElementChild === null
          || !elem.lastElementChild.classList.contains(placeholderClass)) {
          elem.append(placeholderElement.cloneNode());
        }
        if (elem.querySelector(`[data-id='${draggedElement.dataset.id}']`) !== null) {
          elem.querySelector(`.${placeholderClass}`)?.remove();
        }
      },
    );
  }
}

export default function matchingHandler(
  parentElementParam,
  makeDraggable,
  matchingTypeParam,
) {
  parentElement = parentElementParam;
  matchingType = matchingTypeParam;
  setup();
  makeDraggable(
    matchingType === matchingTypeOneToOne ? 'move' : 'copy',
    parentElement,
    termElementClass,
    placeholderClass,
    changeHandler,
    onStartPrepareHandler,
  );
}
