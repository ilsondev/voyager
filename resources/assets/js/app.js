import '../sass/app.scss';
import * as Vue from 'vue';
window.Vue = Vue;
import { createAdminApp } from './voyager-vue';
window.VoyagerVue = { createAdminApp };
import jQuery from 'jquery';
window.jQuery = jQuery;
window.$ = jQuery;
import PerfectScrollbar from 'perfect-scrollbar';
window.Cropper = require('cropperjs');
window.Cropper = 'default' in window.Cropper ? window.Cropper['default'] : window.Cropper;
window.toastr = require('toastr');
require('datatables.net');
require('datatables.net-bs5');
window.EasyMDE = require('easymde');
require('dropzone');
require('jquery-match-height');
require('nestable2');
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
require('select2');
import { TempusDominus } from '@eonasdan/tempus-dominus';
window.TempusDominus = TempusDominus;
var brace = require('brace');
require('brace/mode/json');
require('brace/theme/github');
require('./slugify');
window.TinyMCE = window.tinymce = require('tinymce');
require('./multilingual');
require('./voyager_tinymce');
window.voyagerTinyMCE = require('./voyager_tinymce_config');
require('./voyager_ace_editor');
window.helpers = require('./helpers.js');

var admin_menu = createAdminApp({}, {
    'admin-menu': require('./components/admin_menu.vue').default,
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
