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
 * C4L Modal for Tiny.
 *
 * @module      tiny_c4l/modal
 * @copyright   2022 Marc Català <reskit@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import ModalRegistry from 'core/modal_registry';

const C4LModal = class extends Modal {
    static TYPE = 'tiny_c4l/modal';
    static TEMPLATE = 'tiny_c4l/modal';

    configure(modalConfig) {
        // Remove modal from DOM on close.
        modalConfig.removeOnClose = true;
        super.configure(modalConfig);
    }

    registerEventListeners() {
        // Call the parent registration.
        super.registerEventListeners();
    }
};

ModalRegistry.register(C4LModal.TYPE, C4LModal, C4LModal.TEMPLATE);

export default C4LModal;
