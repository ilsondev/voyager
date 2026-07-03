import '../sass/app.scss';
// Must be imported first: some jQuery plugins below (e.g. nestable2) read
// window.jQuery/$ at module-evaluation time rather than doing their own
// `require('jquery')`, and ES imports all evaluate before this file's own
// body code would otherwise set those globals.
import jQuery from './jquery-globals';
// Imported under a non-`Vue` local name on purpose: exposing a bare top-level
// `Vue` binding here makes the minifier keep "Vue" as a live identifier, which
// then collides with a library internal that gets mangled to the same name
// (perfect-scrollbar's element-matches helper), breaking it at runtime. The
// public global that Blade inline scripts read stays `window.Vue`.
import * as VueRuntime from 'vue';
window.Vue = VueRuntime;
import { createAdminApp } from './voyager-vue';
window.VoyagerVue = { createAdminApp };
import PerfectScrollbar from 'perfect-scrollbar';
import Cropper from 'cropperjs';
window.Cropper = Cropper;
import toastr from 'toastr';
window.toastr = toastr;
import 'datatables.net';
import 'datatables.net-bs5';
import EasyMDE from 'easymde';
window.EasyMDE = EasyMDE;
import 'dropzone';
import 'jquery-match-height';
import 'nestable2';
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
// select2's CJS build exports a factory that must be explicitly invoked with
// (root, jQuery) to actually register $.fn.select2 — a plain side-effect
// import leaves the plugin's factory uncalled.
import select2 from 'select2';
select2(window, jQuery);
import { TempusDominus } from '@eonasdan/tempus-dominus';
window.TempusDominus = TempusDominus;
import 'brace';
import 'brace/mode/json';
import 'brace/theme/github';
import './slugify';
import tinymce from 'tinymce';
window.TinyMCE = window.tinymce = tinymce;
import './multilingual';
import './voyager_tinymce';
import * as voyagerTinyMCE from './voyager_tinymce_config';
window.voyagerTinyMCE = voyagerTinyMCE;
import './voyager_ace_editor';
import * as helpers from './helpers.js';
window.helpers = helpers;
import AdminMenu from './components/admin_menu.vue';

var admin_menu = createAdminApp({}, {
    'admin-menu': AdminMenu,
}).mount('#adminmenu');

$(document).ready(function () {
    var appContainer = $(".app-container"),
        fadedOverlay = $('.fadetoblack'),
        hamburger = $('.hamburger');

    new PerfectScrollbar('.side-menu');

    $('#voyager-loader').fadeOut();

    $(".hamburger, .navbar-expand-toggle").on('click', function () {
        appContainer.toggleClass("expanded");
        $(this).toggleClass('is-active');
        if ($(this).hasClass('is-active')) {
            window.localStorage.setItem('voyager.stickySidebar', true);
        } else {
            window.localStorage.setItem('voyager.stickySidebar', false);
        }
    });

    $('select.select2').select2({width: '100%'});
    $('select.select2-ajax').each(function() {
        $(this).select2({
            width: '100%',
            tags: $(this).hasClass('taggable'),
            createTag: function(params) {
                var term = $.trim(params.term);
    
                if (term === '') {
                    return null;
                }
    
                return {
                    id: term,
                    text: term,
                    newTag: true
                }
            },
            ajax: {
                url: $(this).data('get-items-route'),
                data: function (params) {
                    var query = {
                        search: params.term,
                        type: $(this).data('get-items-field'),
                        method: $(this).data('method'),
                        id: $(this).data('id'),
                        page: params.page || 1
                    }
                    return query;
                }
            }
        });

        $(this).on('select2:select',function(e){
            var data = e.params.data;
            if (data.id == '') {
                // "None" was selected. Clear all selected options
                $(this).val([]).trigger('change');
            } else {
                $(e.currentTarget).find("option[value='" + data.id + "']").attr('selected','selected');
            }
        });

        $(this).on('select2:unselect',function(e){
            var data = e.params.data;
            $(e.currentTarget).find("option[value='" + data.id + "']").attr('selected',false);
        });

        $(this).on('select2:selecting', function(e) {
            if (!$(this).hasClass('taggable')) {
                return;
            }
            var $el = $(this);
            var route = $el.data('route');
            var label = $el.data('label');
            var errorMessage = $el.data('error-message');
            var newTag = e.params.args.data.newTag;
    
            if (!newTag) return;
    
            $el.select2('close');
    
            $.post(route, {
                [label]: e.params.args.data.text,
                _tagging: true,
            }).done(function(data) {
                var newOption = new Option(e.params.args.data.text, data.data.id, false, true);
                $el.append(newOption).trigger('change');
            }).fail(function(error) {
                toastr.error(errorMessage);
            });
    
            return false;
        });
    });

    $('.match-height').matchHeight();

    $('.datatable').DataTable({
        "dom": '<"top"fl<"clear">>rt<"bottom"ip<"clear">>'
    });

    $(".side-menu .nav .dropdown").on('show.bs.collapse', function () {
        $(".side-menu .nav .dropdown .collapse").each(function () {
            bootstrap.Collapse.getOrCreateInstance(this, { toggle: false }).hide();
        });
    });

    $('.panel-collapse').on('hide.bs.collapse', function(e) {
        var target = $(e.target);
        if (!target.is('a')) {
            target = target.parent();
        }
        if (!target.hasClass('collapsed')) {
            return;
        }
        e.stopPropagation();
        e.preventDefault();
    });

    // Voyager's own hand-rolled panel collapse/fullscreen toggle (data-toggle="panel-collapse"/
    // "panel-fullscreen" are custom action names, not Bootstrap's data-bs-toggle). .card/.card-header/
    // .card-body match the panel -> card markup rename done throughout the admin theme.
    $(document).on('click', '.card-header a.panel-action[data-toggle="panel-collapse"]', function (e) {
        e.preventDefault();
        var $this = $(this);

        // Toggle Collapse
        if (!$this.hasClass('panel-collapsed')) {
            $this.parents('.card').find('.card-body').slideUp();
            $this.addClass('panel-collapsed');
            $this.removeClass('voyager-angle-up').addClass('voyager-angle-down');
        } else {
            $this.parents('.card').find('.card-body').slideDown();
            $this.removeClass('panel-collapsed');
            $this.removeClass('voyager-angle-down').addClass('voyager-angle-up');
        }
    });

    //Toggle fullscreen
    $(document).on('click', '.card-header a.panel-action[data-toggle="panel-fullscreen"]', function (e) {
        e.preventDefault();
        var $this = $(this);
        if (!$this.hasClass('voyager-resize-full')) {
            $this.removeClass('voyager-resize-small').addClass('voyager-resize-full');
        } else {
            $this.removeClass('voyager-resize-full').addClass('voyager-resize-small');
        }
        $this.closest('.card').toggleClass('is-fullscreen');
    });

    $('.datepicker').each(function (idx, elt) {
        new TempusDominus(elt);
    });

    // Save shortcut
    $(document).keydown(function (e) {
        if ((e.metaKey || e.ctrlKey) && e.keyCode == 83) { /*ctrl+s or command+s*/
            $(".btn.save").click();
            e.preventDefault();
            return false;
        }
    });

    /********** MARKDOWN EDITOR **********/

    $('textarea.easymde').each(function () {
        var easymde = new EasyMDE({
            element: this
        });
        easymde.render();
    });

    /********** END MARKDOWN EDITOR **********/

});
