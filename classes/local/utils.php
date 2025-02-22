<?php
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

namespace tiny_c4l\local;

/**
 * Utility class for tiny_c4l.
 *
 * @package    tiny_c4l
 * @copyright  2024 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /** @var string TINY_C4L_CACHE_AREA the cache area for the tiny_c4l plugin */
    public const TINY_C4L_CACHE_AREA = 'tiny_c4l_css';

    /** @var string TINY_C4L_JS_CACHE_KEY the cache key for the js code */
    public const TINY_C4L_JS_CACHE_KEY = 'tiny_c4l_js';

    /** @var string TINY_C4L_CSS_CACHE_KEY the cache key for the css code */
    public const TINY_C4L_CSS_CACHE_KEY = 'css';

    /** @var string TINY_C4L_CSS_CACHE_REV the cache key for the css revision */
    public const TINY_C4L_CSS_CACHE_REV = 'cssrev';

    /** @var string TINY_C4L_JS_CACHE_REV the cache key for the js revision */
    public const TINY_C4L_JS_CACHE_REV = 'jsrev';

    /**
     * Get all components.
     * @param bool $isstudent
     * @return array all components
     */
    public static function get_all_components(bool $isstudent = false): array {
        global $DB;
        $componentrecords = $DB->get_records('tiny_c4l_component', null, 'displayorder');
        $components = [];
        foreach ($componentrecords as $record) {
            $components[] = [
                    'id' => $record->id,
                    'name' => $record->name,
                    'displayname' => $record->displayname,
                    'compcat' => $record->compcat,
                    'imageclass' => $record->imageclass, // not in db table? delete?
                    'code' => self::replace_pluginfile_urls($record->code, true),
                    'text' => $record->text,
                    'displayorder' => $record->displayorder,
                    'flavors' => explode(',', $record->flavors), // not in db table? maybe 'flavors' => '';
                    'variants' => explode(',', $record->variants),  // not in db table?  maybe 'variants' => '';
                    'js' => self::replace_pluginfile_urls($record->js, true),
            ];
        }
        return $components;
    }

    /**
     * Get all variants.
     * @param bool $isstudent
     * @return array all variants
     */
    public static function get_all_variants(bool $isstudent = false): array {
        global $DB;
        $variants = $DB->get_records('tiny_c4l_variant');
        foreach ($variants as $variant) {
            $variant->content = self::replace_pluginfile_urls($variant->content, true);
        }
        return $variants;
    }

    /**
     * Get all component variants.
     * 
     * @param bool $isstudent
     * @return array all component variants
     */
    public static function get_all_comp_variants(bool $isstudent = false): array {
        global $DB;
        $compvariants = $DB->get_records('tiny_c4l_comp_variant', null, '', 'id, component, variant');
        // Sort all variants to the component. key: component id, value: array of variantsnames.
        $components = [];
        foreach ($compvariants as $compvariant) {
            $components[$compvariant->component] = array_merge([$compvariant->variant], $components[$compvariant->component] ?? []);
        }
        return $components;
    }

    /**
     * Get all component categories.
     * @param bool $isstudent
     * @return array all component categories
     */
    public static function get_all_compcats(bool $isstudent = false): array {
        global $DB;
        $categories = $DB->get_records('tiny_c4l_compcat', null, 'displayorder');
        return array_values($categories);
    }

    /**
     * Get all component flavors.
     * @param bool $isstudent
     * @return array all component flavors
     */
    public static function get_all_comp_flavors(bool $isstudent = false): array {
        global $DB;
        $compflavors = $DB->get_records('tiny_c4l_comp_flavor', null, '', 'id, componentname, flavorname');
        $components = [];
        foreach ($compflavors as $compflavor) {
            $components[$compflavor->componentname] = array_merge([$compflavor->flavorname], $components[$compflavor->componentname] ?? []);
        }
        return $components;
    }

    /**
     * Get all flavors.
     * @param bool $isstudent
     * @return array all flavors
     */
    public static function get_all_flavors(bool $isstudent = false): array {
        global $DB;
        $flavors = $DB->get_records('tiny_c4l_flavor');
        $flavorsbyname = [];
        foreach ($flavors as $flavor) {
            $flavorsbyname[$flavor->name] = $flavor;
            $flavorsbyname[$flavor->name]->categories = [];
            $flavorsbyname[$flavor->name]->content = self::replace_pluginfile_urls($flavor->content, true);
        }
        return $flavorsbyname;
    }

    /**
     * Get all data for the c4l editor.
     * @param bool $isstudent
     * @return array all data for the c4l editor
     */
    public static function get_c4l_data(bool $isstudent = false): array {
        $components = self::get_all_components($isstudent);
        $compcats = self::get_all_compcats($isstudent);
        $flavors = self::get_all_flavors($isstudent);
        $variants = self::get_all_variants($isstudent);
        $componentflavors = self::get_all_comp_flavors($isstudent);
        $componentvariants = self::get_all_comp_variants($isstudent);

        foreach ($components as $key => $component) {
            // Add flavors to components structure.
            $components[$key]['flavors'] = $componentflavors[$component['name']] ?? [];
            // Add categories to flavors.
            foreach ($components[$key]['flavors'] as $flavor) {
                if (!isset($flavors[$flavor])) {
                    continue;
                }
                $flavors[$flavor]->categories[] = $component['compcat'];
            }

            // Add variants to components structure.
            $components[$key]['variants'] = $componentvariants[$component['id']] ?? [];
        }

        foreach ($flavors as $flavor) {
            $flavor->categories = join(',', array_unique($flavor->categories));
        }

        return [
                'components' => $components,
                'categories' => $compcats,
                'flavors' => $flavors,
                'variants' => $variants,
        ];
    }

    /**
     * Rebuild the css cache.
     *
     * @return int the new revision for the cache
     */
    public static function rebuild_css_cache(): int {
        global $DB;
        $cache = \cache::make('tiny_c4l', self::TINY_C4L_CACHE_AREA);
        $iconcssentries = [];
        $componentcssentries = [];
        $variantscssentries = [];
        $components = [];
        $componentshideforstudents = [];
        $flavorshideforstudents = [];
        $variants = [];
        try {
            $components = $DB->get_records('tiny_c4l_component', null, '', 'id, name, css, iconurl, hideforstudents');
            $categorycssentries = $DB->get_fieldset('tiny_c4l_compcat', 'css');
            $flavors = $DB->get_records('tiny_c4l_flavor', null, 'id, name, hideforstudents');
            $flavorcssentries = $DB->get_fieldset('tiny_c4l_flavor', 'css');
            $variants = $DB->get_records('tiny_c4l_variant', null, '', 'name, iconurl, css');
        } catch (\dml_exception $e) {
            // This is done to prevent the plugin from crashing the whole site if the database tables
            // are not yet installed for some reason.
            return 0;
        }
        foreach ($variants as $variant) {
            $variantscssentries[] = $variant->css;
            if (empty($variant->iconurl)) {
                continue;
            }
            $iconcssentries[] = self::variant_icon_css($variant->name, self::replace_pluginfile_urls($variant->iconurl, true));
        }
        $componentflavors = $DB->get_records('tiny_c4l_comp_flavor');
        foreach ($componentflavors as $componentflavor) {
            if (empty($componentflavor->iconurl)) {
                continue;
            }
            $iconcssentries[] .= self::button_icon_css($componentflavor->componentname, self::replace_pluginfile_urls($componentflavor->iconurl, true), $componentflavor->flavorname);
        }
        foreach ($components as $component) {
            if ($component->hideforstudents) {
                $componentshideforstudents[] .= self::hide_item_css($component->name, 'component');
            }
            $componentcssentries[] = $component->css;
            if (empty($component->iconurl)) {
                continue;
            }
            $iconcssentries[] .= self::button_icon_css($component->name, self::replace_pluginfile_urls($component->iconurl, true));
        }
        foreach ($flavors as $flavor) {
            if ($flavor->hideforstudents) {
                $flavorshideforstudents[] .= self::hide_item_css($flavor->name, 'flavor');
            }
        }
        $cssentries = array_merge(
            $categorycssentries,
            $componentcssentries,
            $flavorcssentries,
            $variantscssentries,
            $iconcssentries,
            $componentshideforstudents,
            $flavorshideforstudents,
        );
        $css = array_reduce(
            $cssentries,
            fn($current, $add) => $current . PHP_EOL . $add,
            '/* This file contains the stylesheet for the tiny_c4l plugin.*/'
        );
        $css = self::replace_pluginfile_urls($css, true);
        $clock = \core\di::get(\core\clock::class);
        $rev = $clock->time();
        $cache->set(self::TINY_C4L_CSS_CACHE_KEY, $css);
        $cache->set(self::TINY_C4L_CSS_CACHE_REV, $rev);
        return $rev;
    }

    /**
     * Rebuild the js cache.
     * @return int the new revision for the cache
     */
    public static function rebuild_js_cache(): int {
        global $DB;
        $cache = \cache::make('tiny_c4l', self::TINY_C4L_CACHE_AREA);
        $jsentries = [];
        try {
            $jsentries = $DB->get_records_menu('tiny_c4l_component', null, '', 'id, js');
        } catch (\dml_exception $e) {
            // This is done to prevent the plugin from crashing the whole site if the database tables
            // are not yet installed for some reason.
            return 0;
        }
        $js = array_reduce(
            $jsentries,
            fn($current, $add) => $current . PHP_EOL . $add,
            '/* This file contains the javascript for the tiny_c4l plugin.*/'
        );
        $js = self::replace_pluginfile_urls($js, true);
        $cache->set(self::TINY_C4L_JS_CACHE_KEY, $js);
        $clock = \core\di::get(\core\clock::class);
        $rev = $clock->time();
        $cache->set(self::TINY_C4L_JS_CACHE_REV, $rev);
        return $rev;
    }

    /**
     * Purge the tiny_c4l css cache.
     */
    public static function purge_css_cache(): void {
        $cache = \cache::make('tiny_c4l', self::TINY_C4L_CACHE_AREA);
        $cache->delete(self::TINY_C4L_CSS_CACHE_KEY);
    }

    /**
     * Purge the tiny_c4l js cache.
     */
    public static function purge_js_cache(): void {
        $cache = \cache::make('tiny_c4l', self::TINY_C4L_CACHE_AREA);
        $cache->delete(self::TINY_C4L_JS_CACHE_KEY);
    }

    /**
     * Helper function to retrieve the currently cached tiny_c4l css.
     *
     * @return string|false the css code as string, false if no cache entry found
     */
    public static function get_css_from_cache(): string|false {
        $cache = \cache::make('tiny_c4l', self::TINY_C4L_CACHE_AREA);
        return $cache->get(self::TINY_C4L_CSS_CACHE_KEY);
    }

    /**
     * Helper function to retrieve the currently cached tiny_c4l js.
     *
     * @return string|false the js code as string, false if no cache entry found
     */
    public static function get_js_from_cache(): string|false {
        $cache = \cache::make('tiny_c4l', self::TINY_C4L_CACHE_AREA);
        return $cache->get(self::TINY_C4L_JS_CACHE_KEY);
    }

    /**
     * Replace @@PLUGINFILE@@ with the correct URL and vice versa.
     *
     * @param $content
     * @param bool $realurl if true, get the real URL, otherwise replace it
     */
    public static function replace_pluginfile_urls(string $content, bool $realurl = false): string {
        global $CFG;
        if (!$realurl) {
            $content = str_replace($CFG->wwwroot . '/pluginfile.php', '@@PLUGINFILE@@', $content);
        } else {
            $content = str_replace('@@PLUGINFILE@@', $CFG->wwwroot . '/pluginfile.php', $content);
        }
        return $content;
    }

    /**
     * Get the css for a button with an icon.
     *
     * @param string $buttonclass
     * @param string $iconurl
     * @return string
     */
    public static function variant_icon_css(string $variant, string $iconurl): string {
        return <<<CSS
        .c4l-button-variant[data-variant="{$variant}"] {
            background-image: url('{$iconurl}');
        }
        CSS;
    }

    /**
     * Get the css for an icon.
     *
     * @param string $buttonclass
     * @param string $iconurl
     * @param string $variant
     * @return string
     */
    public static function button_icon_css(string $buttonclass, string $iconurl, string $variant = ''): string {
        $variant = empty($variant) ? '' : '.' . $variant;
        return <<<CSS
        .c4l-{$buttonclass}-icon{$variant} .c4l-button-text::before {
            content: url('{$iconurl}');
        }
        CSS;
    }

    /**
     * Get the css to hide for pupils.
     *
     * @param string $name
     * @param string $type
     * @return string
     */
    public static function hide_item_css(string $name, string $type): string {
        if ($type == 'component') {
            return <<<CSS
            body.tiny_c4l_h4s .c4l-buttons-preview button[class^='c4l-{$name}-icon'],
            body.tiny_c4l_h4s .c4l-buttons-preview button[class*='c4l-{$name}-icon'] {
                display: none;
            }
            CSS;
        } else if ($type == 'flavor') {
            return <<<CSS
            body.tiny_c4l_h4s .c4l-buttons-flavors button[data-flavor='{$name}'] {
                display: none;
            }
            body.tiny_c4l_h4s .c4l-buttons-preview button[data-flavor='{$name}'] {
                display: none;
            }
            CSS;
        }
    }
}
