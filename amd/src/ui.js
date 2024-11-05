// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tiny C4L UI.
 *
 * @module      tiny_c4l/ui
 * @copyright   2022 Marc Català <reskit@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {component} from './common';
import C4LModal from './modal';
import ModalFactory from 'core/modal_factory';
import {get_strings as getStrings} from 'core/str';
import {
    isStudent,
    showPreview
} from './options';
import ModalEvents from 'core/modal_events';
import {
    addVariant,
    getVariantsClass,
    getVariantHtml,
    getVariantPreferences,
    getVariantsHtml,
    loadVariantPreferences,
    removeVariant,
    setFlavors,
    setVariants,
    variantExists,
    setComponents
} from './variantslib';
import {
    findByName
} from './helper';
import {
    savePreferences,
    loadPreferences,
    Preferences
} from './preferencelib';
import {call as fetchMany} from 'core/ajax';

let userStudent = false;

let previewC4L = true;
let components = [];
let categories = [];
let flavors = [];
let variants = [];
let langStrings = {};
let contextid = 1;

let currentFlavor = '';
let currentFlavorId = 0;
let currentCategoryId = 1;
let lastFlavor = [];

/**
 * Handle action
 *
 * @param {TinyMCE} editor
 */
export const handleAction = async(editor) => {
    userStudent = isStudent(editor);
    let data = await getC4LData();
    components = data.components;
    categories = data.categories;
    flavors = data.flavors;
    variants = data.variants;
    setComponents(components);
    setVariants(variants);
    setFlavors(flavors);
    previewC4L = showPreview(editor);
    langStrings = await getAllStrings();
    currentCategoryId = await loadPreferences(Preferences.category);
    lastFlavor = await loadPreferences(Preferences.category_flavors);
    if (lastFlavor === null) {
        lastFlavor = [];
    }
    let component_variants = await loadPreferences(Preferences.component_variants);
    if (component_variants === null) {
        component_variants = {};
    }
    loadVariantPreferences(component_variants);
    displayDialogue(editor);
};

/**
 * Display modal
 *
 * @param  {TinyMCE} editor
 */
const displayDialogue = async(editor) => {
    const data = Object.assign({}, {});
    const templateContext = await getTemplateContext(editor, data);
    // Show modal with buttons.
    const modal = await ModalFactory.create({
        type: C4LModal.TYPE,
        templateContext: templateContext,
        large: true,
    });

    // Set modal size when no preview.
    if (!previewC4L) {
        editor.targetElm.closest('body').classList.add('c4l-modal-no-preview');
    }
    modal.show();

    // Event modal listener.
    modal.getRoot().on(ModalEvents.hidden, () => {
        handleModalHidden(editor);
    });

    // Event filters listener.
    const filters = modal.getRoot()[0].querySelectorAll('.c4l-button-filter');
    filters.forEach(node => {
        node.addEventListener('click', (event) => {
            handleButtonFilterClick(event, modal);
        });
    });

    // Event flavor selector listener.
    const flavorbuttons = modal.getRoot()[0].querySelectorAll('.c4l-button-flavor');
    flavorbuttons.forEach(node => {
        node.addEventListener('click', (event) => {
            handleButtonFlavorClick(event, modal);
        });
    });

    // Event buttons listeners.
    const buttons = modal.getRoot()[0].querySelectorAll('.c4lt-dialog-button');
    buttons.forEach(node => {
        node.addEventListener('click', (event) => {
            handleButtonClick(event, editor, modal);
        });
        if (previewC4L) {
            node.addEventListener('mouseenter', (event) => {
                handleButtonMouseEvent(event, modal, true);
            });
            node.addEventListener('mouseleave', (event) => {
                handleButtonMouseEvent(event, modal, false);
            });
        }
    });

    // Event variants listeners.
    const variants = modal.getRoot()[0].querySelectorAll('.c4l-button-variant');
    variants.forEach(node => {
        node.addEventListener('click', (event) => {
            handleVariantClick(event, modal);
        });
    });

    if (filters.length > 0) {
        let savedCategory = currentCategoryId;
        filters[0].click();
        if (savedCategory != 0) {
            filters.forEach((filter) => {
                if (filter.dataset.filter == savedCategory) {
                    filter.click();
                }
            });
        }
    }

    clickFlavor(modal, lastFlavor[currentCategoryId] ? lastFlavor[currentCategoryId] : 0);
};

/**
 * Handle a click within filter button.
 *
 * @param {MouseEvent} event The change event
 * @param {obj} modal
 */
const handleButtonFilterClick = (event, modal) => {
    const button = event.target.closest('button');
    currentCategoryId = button.dataset.filter;

    const buttons = modal.getRoot()[0].querySelectorAll('.c4l-buttons-filters button');
    buttons.forEach(node => node.classList.remove('c4l-button-filter-enabled'));
    button.classList.add('c4l-button-filter-enabled');

    showFlavors(modal, currentCategoryId);

    // Show/hide component buttons.
    showCategoryButtons(modal, currentCategoryId);

    clickFlavor(modal, lastFlavor[currentCategoryId] ? lastFlavor[currentCategoryId] : 0);
};

const clickFlavor = (modal, flavor = 0) => {
    if (flavor == 0) {
        let availableFlavors = modal.getRoot()[0].querySelectorAll('.c4l-button-flavor:not(.c4l-hidden)');
        if (availableFlavors.length > 0) {
            availableFlavors[0].click();
        } else {
            let componentButtons = modal.getRoot()[0].querySelectorAll('.c4l-buttons-preview button');
            componentButtons.forEach(componentButton => {
                if (componentButton.dataset.flavor != undefined) {
                    componentButton.classList.remove(componentButton.dataset.flavor);
                    componentButton.removeAttribute('data-flavor');
                }
            });
        }
        return;
    }

    let flavorButtons = modal.getRoot()[0].querySelectorAll('.c4l-buttons-flavors button');
    flavorButtons.forEach(node => {
        if (node.dataset.id == flavor) {
            node.click();
        }
    });
};

const showFlavors = (modal, categoryId) => {
    const flavorButtons = modal.getRoot()[0].querySelectorAll('.c4l-button-flavor');
    flavorButtons.forEach(node => {
        node.classList.remove('c4l-button-flavor-enabled');
        let categories = node.dataset.categories.split(',');
        if (categories.length == 0 || categories.includes(categoryId)) {
            node.classList.remove('c4l-hidden');
        } else {
            node.classList.add('c4l-hidden');
        }
    });
};

const handleButtonFlavorClick = (event, modal) => {
    const button = event.target.closest('button');
    currentFlavor = button.dataset.flavor;
    currentFlavorId = button.dataset.id;
    lastFlavor[currentCategoryId] = currentFlavorId;

    const buttons = modal.getRoot()[0].querySelectorAll('.c4l-buttons-flavors button');
    buttons.forEach(node => node.classList.remove('c4l-button-flavor-enabled'));
    button.classList.add('c4l-button-flavor-enabled');
    const componentButtons = modal.getRoot()[0].querySelectorAll('.c4l-buttons-preview button');

    componentButtons.forEach(componentButton => {
        // Remove previous flavor.
        if (componentButton.dataset.flavor != undefined) {
            componentButton.classList.remove(componentButton.dataset.flavor);
        }
        componentButton.classList.add(currentFlavor);
        componentButton.dataset.flavor = currentFlavor;
        if (
            (componentButton.dataset.flavorlist == '' || componentButton.dataset.flavorlist.split(',').includes(currentFlavor)) &&
            componentButton.dataset.category == currentCategoryId
        ) {
            componentButton.classList.remove('c4l-hidden');
            if (componentButton.dataset.flavorlist != '') {
                let variants = getVariantsClass(components[componentButton.dataset.id].name, currentFlavor);
                let availableVariants = componentButton.querySelectorAll('.c4l-button-variant');
                availableVariants.forEach((variant) => {
                    if (variants.indexOf(variant.dataset.variant) != -1) {
                        updateVariantButtonState(variant, true);
                    } else {
                        updateVariantButtonState(variant, false);
                    }
                });
            }
        } else {
            componentButton.classList.add('c4l-hidden');
        }
    });

};

/**
 * Handle when closing the Modal.
 *
 * @param {obj} editor
 */
const handleModalHidden = (editor) => {
    editor.targetElm.closest('body').classList.remove('c4l-modal-no-preview');
    savePreferences([
        {type: Preferences.category, value: currentCategoryId},
        {type: Preferences.category_flavors, value: JSON.stringify(lastFlavor)},
        {type: Preferences.component_variants, value: JSON.stringify(getVariantPreferences())}
    ]);
};

const updateComponentCode = (componentCode, selectedButton, placeholder, flavor = '') => {
    componentCode = componentCode.replace('{{PLACEHOLDER}}', placeholder);

    // Return active variants for current component.
    const variants = getVariantsClass(components[selectedButton].name, flavor);

    // Apply variants to html component.
    if (variants.length > 0) {
        componentCode = componentCode.replace('{{VARIANTS}}', variants.join(' '));
        componentCode = componentCode.replace('{{VARIANTSHTML}}', getVariantsHtml(components[selectedButton].name));
    } else {
        componentCode = componentCode.replace('{{VARIANTS}}', '');
        componentCode = componentCode.replace('{{VARIANTSHTML}}', '');
    }

    if (currentFlavor) {
        componentCode = componentCode.replace('{{FLAVOR}}', currentFlavor);
    } else {
        componentCode = componentCode.replace('{{FLAVOR}}', '');
    }

    componentCode = componentCode.replace('{{COMPONENT}}', components[selectedButton].name);
    componentCode = componentCode.replace('{{CATEGORY}}', categories[currentCategoryId].name);

    // Apply random IDs.
    componentCode = applyRandomID(componentCode);

    // Apply lang strings.
    componentCode = applyLangStrings(componentCode);

    return componentCode;
};

/**
 * Handle a click in a component button.
 *
 * @param {MouseEvent} event The click event
 * @param {obj} editor
 * @param {obj} modal
 */
const handleButtonClick = async (event, editor, modal) => {
    const selectedButton = event.target.closest('button').dataset.id;

    // Component button.
    if (components[selectedButton]) {
        const sel = editor.selection.getContent();
        let componentCode = components[selectedButton].code;
        const placeholder = (sel.length > 0 ? sel : components[selectedButton].text);

        let flavor = components[selectedButton].flavors.length > 0 ? currentFlavor : '';

        // Create a new node to replace the placeholder.
        const randomId = generateRandomID();
        const newNode = document.createElement('span');
        newNode.dataset.id = randomId;
        newNode.innerHTML = placeholder;
        componentCode = updateComponentCode(componentCode, selectedButton, newNode.outerHTML, flavor);
        // Sets new content.
        editor.selection.setContent(componentCode);

        // Select text.
        const nodeSel = editor.dom.select('span[data-id="' + randomId + '"]');
        if (nodeSel?.[0]) {
            editor.selection.select(nodeSel[0]);
        }

        modal.destroy();
        editor.focus();
    }
};

/**
 * Handle a mouse events mouseenter/mouseleave in a component button.
 *
 * @param {MouseEvent} event The click event
 * @param {obj} modal
 * @param {bool} show
 */
const handleButtonMouseEvent = (event, modal, show) => {
    const selectedButton = event.target.closest('button').dataset.id;
    const node = modal.getRoot()[0].querySelector('div[data-id="code-preview-' + selectedButton + '"]');
    const previewDefault = modal.getRoot()[0].querySelector('div[data-id="code-preview-default"]');
    let flavor = components[selectedButton].flavors.length > 0 ? currentFlavor : '';

    node.innerHTML = updateComponentCode(components[selectedButton].code, selectedButton, components[selectedButton].text, flavor);

    if (node) {
        if (show) {
            previewDefault.classList.toggle('c4l-hidden');
            node.classList.toggle('c4l-hidden');
        } else {
            node.classList.toggle('c4l-hidden');
            previewDefault.classList.toggle('c4l-hidden');
        }
    }
};

/**
 * Handle a mouse events mouseenter/mouseleave in a variant button.
 * Not used at the moment.
 *
 * @param {MouseEvent} event The mouseenter/mouseleave event
 * @param {obj} modal
 * @param {bool} show
 */
// eslint-disable-next-line no-unused-vars
const handleVariantMouseEvent = (event, modal, show) => {
    const variant = event.target.closest('span');
    const variantEnabled = variant.dataset.state == 'on';
    const button = event.target.closest('button');

    if (!variantEnabled) {
        updateVariantComponentState(variant, button, modal, show, false);
    }
};


/**
 * Handle a mouse event within the variant buttons.
 *
 * @param {MouseEvent} event The mouseenter/mouseleave event
 * @param {obj} modal
 */
const handleVariantClick = (event, modal) => {
    event.stopPropagation();
    const variant = event.target.closest('span');
    const button = event.target.closest('button');
    const flavor = components[button.dataset.id].flavors.length > 0 ? currentFlavor : '';

    updateVariantComponentState(variant, button, modal, false, true);

    const node = modal.getRoot()[0].querySelector('div[data-id="code-preview-' + button.dataset.id + '"]');
    node.innerHTML = updateComponentCode(
        components[button.dataset.id].code,
        button.dataset.id,
        components[button.dataset.id].text,
        flavor
    );
};

/**
 * Get the template context for the dialogue.
 *
 * @param {Editor} editor
 * @param {object} data
 * @returns {object} data
 */
const getTemplateContext = async(editor, data) => {
    return Object.assign({}, {
        elementid: editor.id,
        buttons: await getButtons(editor),
        filters: await getFilters(),
        flavors: flavors,
        preview: previewC4L,
    }, data);
};

/**
 * Get the C4L filters for the dialogue.
 *
 * @returns {object} data
 */
const getFilters = async() => {
    const filters = [];
    //const stringValues = await getStrings(Contexts.map((key) => ({key, component})));
    // Iterate over contexts.
    categories.forEach((category) => {
        filters.push({
            id: category.id,
            name: category.displayname,
            type: category.id,
            filterClass: category.order === 1 ? 'c4l-button-filter-enabled' : '',
            displayorder: category.displayorder,
        });
    });
    filters.sort((a, b) => a.displayorder - b.displayorder);

    return filters;
};

const getComponentVariants = (component) => {
    const componentVariants = [];
    component.variants.forEach(variant => {
        let variantitem = findByName(variants, variant);
        if (variantitem !== undefined) {
            let state = variantExists(component.name, variantitem.name) ? 'on' : 'off';
            componentVariants.push({
                id: variantitem.id,
                name: variantitem.name,
                state: state,
                imageClass: variantitem.name + '-variant-' + state,
                title: langStrings.get(variantitem.name),
                content: variantitem.content,
            });
        }
    });
    return componentVariants;
};

/**
 * Get the C4L buttons for the dialogue.
 *
 * @param {Editor} editor
 * @returns {object} buttons
 */
const getButtons = async(editor) => {
    const buttons = [];
    // Not used at the moment.
    // eslint-disable-next-line no-unused-vars
    const sel = editor.selection.getContent();
    Object.values(components).forEach(component => {
        buttons.push({
            id: component.id,
            name: component.displayname,
            type: component.compcat,
            imageClass: 'c4l-' + component.name + '-icon',
            htmlcode: component.code,
            variants: getComponentVariants(component, variants),
            flavorlist: component.flavors.join(','),
            category: component.compcat,
        });
    });
    buttons.sort((a, b) => a.displayorder - b.displayorder);

    return buttons;
};

const getC4LData = async() => {
    const data = await fetchMany([{
        methodname: 'tiny_c4l_get_c4l_data',
        args: {
            isstudent: userStudent,
            contextid: contextid
        },
    }])[0];

    // TODO error handling
    const indexedComponents = [];
    data.components.forEach(component => {
        indexedComponents[component.id] = component;
    });

    const indexedVariants = [];
    data.variants.forEach(variant => {
        indexedVariants[variant.id] = variant;
    });

    const indexedCategories = [];
    data.categories.forEach(category => {
        indexedCategories[category.id] = category;
    });

    return {
        components: indexedComponents,
        variants: indexedVariants,
        categories: indexedCategories,
        flavors: data.flavors,
    };
};

/**
 * Get variants for the dialogue.
 * Not used at the moment.
 *
 * @param  {string} component
 * @param  {object} elements
 * @return {object} Variants for a component
 */
// eslint-disable-next-line no-unused-vars
const getVariantsState = (component, elements) => {
    const variants = [];
    let variantState = '';
    let variantClass = '';

    // Max 3 variants.
    if (elements.length > 3) {
        elements = elements.slice(0, 2);
    }

    elements.forEach((variant, index) => {
        if (variantExists(component, variant)) {
            variantState = 'on';
            variantClass = 'on ';
        } else {
            variantState = 'off';
            variantClass = '';
        }
        variantClass += variant + '-variant-' + variantState;
        variants.push({
            id: index,
            name: variant,
            state: variantState,
            imageClass: variantClass,
            title: langStrings.get(variant),
        });
    });

    return variants;
};

/**
 * Update a variant component UI.
 *
 * @param {obj} variant
 * @param {obj} button
 * @param {obj} modal
 * @param {bool} show
 * @param {bool} updateHtml
 */
const updateVariantComponentState = (variant, button, modal, show, updateHtml) => {
    const selectedVariant = 'c4l-' + variant.dataset.variant + '-variant';
    const selectedButton = button.dataset.id;
    const componentClass = button.dataset.classcomponent;
    const previewComponent = modal.getRoot()[0]
        .querySelector('div[data-id="code-preview-' + selectedButton + '"] .' + componentClass);
    const variantPreview = modal.getRoot()[0]
        .querySelector('span[data-id="variantHTML-' + selectedButton + '"]');
    let variantsHtml = '';
    let hasflavors = components[selectedButton].flavors.length > 0;

    if (previewComponent) {
        if (updateHtml) {
            if (variant.dataset.state == 'on') {
                removeVariant(components[selectedButton].name, variant.dataset.variant, hasflavors ? currentFlavor : '');
                updateVariantButtonState(variant, false);
                previewComponent.classList.remove(selectedVariant);
            } else {
                addVariant(components[selectedButton].name, variant.dataset.variant, hasflavors ? currentFlavor : '');
                updateVariantButtonState(variant, true);
                previewComponent.classList.add(selectedVariant);
            }

            // Update variant preview HTML.
            if (variantPreview) {
                variantPreview.innerHTML = getVariantsHtml(components[selectedButton].name);
            }
        } else {
            variantsHtml = getVariantsHtml(components[selectedButton].name);
            if (show) {
                previewComponent.classList.add(selectedVariant);
                variantsHtml += getVariantHtml(variant.dataset.variant);
            } else {
                previewComponent.classList.remove(selectedVariant);
            }

            // Update variant preview HTML.
            if (variantPreview) {
                variantPreview.innerHTML = variantsHtml;
            }
        }
    } else {
        // Update variants preferences.
        if (variant.dataset.state == 'on') {
            removeVariant(components[selectedButton].name, variant.dataset.variant, hasflavors ? currentFlavor : '');
            updateVariantButtonState(variant, false);
        } else {
            addVariant(components[selectedButton].name, variant.dataset.variant, hasflavors ? currentFlavor : '');
            updateVariantButtonState(variant, true);
        }
    }
};

/**
 * Update a variant button UI.
 *
 * @param {obj} variant
 * @param {bool} activate
 */
const updateVariantButtonState = (variant, activate) => {
    if (activate) {
        variant.dataset.state = 'on';
        variant.classList.remove(variant.dataset.variant + '-variant-off');
        variant.classList.add(variant.dataset.variant + '-variant-on');
        variant.classList.add('on');
    } else {
        variant.dataset.state = 'off';
        variant.classList.remove(variant.dataset.variant + '-variant-on');
        variant.classList.add(variant.dataset.variant + '-variant-off');
        variant.classList.remove('on');
    }
};

/**
 * Show/hide buttons depend on selected context.
 *
 * @param  {object} modal
 * @param  {String} context
 */
const showCategoryButtons = (modal, context) => {
    const showNodes = modal.getRoot()[0].querySelectorAll('button[data-type="' + context + '"]');
    const hideNodes = modal.getRoot()[0].querySelectorAll('button[data-type]:not([data-type="' + context + '"])');

    showNodes.forEach(node => node.classList.remove('c4l-hidden'));
    hideNodes.forEach(node => node.classList.add('c4l-hidden'));
};

/**
 * Replace all localized strings.
 *
 * @param  {String} text
 * @return {String} String with lang tags replaced with a localized string.
 */
const applyLangStrings = (text) => {
    const compRegex = /{{#([^}]*)}}/g;

    [...text.matchAll(compRegex)].forEach(strLang => {
        text = text.replace('{{#' + strLang[1] + '}}', langStrings.get(strLang[1]));
    });

    return text;
};

/**
 * Generates a random string.
 * @return {string} A random string
 */
const generateRandomID = () => {
    const timestamp = new Date().getTime();
    return 'R' + Math.round(Math.random() * 100000) + '-' + timestamp;
};

/**
 * Replace all ID tags with a random string.
 * @param  {String} text
 * @return {String} String with all ID tags replaced with a random string.
 */
const applyRandomID = (text) => {
    const compRegex = /{{@ID}}/g;

    if (text.match(compRegex)) {
        text = text.replace(compRegex, generateRandomID());
    }

    return text;
};

/**
 * Get language strings.
 *
 * @return {object} Language strings
 */
const getAllStrings = async() => {
    const keys = [];
    const compRegex = /{{#([^}]*)}}/g;

    components.forEach(element => {
        // Get lang strings from components.
        [...element.code.matchAll(compRegex)].forEach(strLang => {
            if (keys.indexOf(strLang[1]) === -1) {
                keys.push(strLang[1]);
            }
        });
    });

    const stringValues = await getStrings(keys.map((key) => ({key, component})));
    return new Map(keys.map((key, index) => ([key, stringValues[index]])));
};
