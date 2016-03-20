/*jslint browser: true, nomen: true, plusplus: true */

/*!
 * @version 2014-02-28 / 2014-01-16
 */
(function ($) {
    'use strict';

    /**
     * @param {string|Element|jQuery} selector
     * @constructor
     */
    function Blockform(selector, options) { // {{{
        var elem = $(selector).first(),
            that = this,
            minBlocks,
            maxBlocks;

        if (0 === elem.length) {
            throw new Blockform.Error('Selector did not match any elements');
        }

        minBlocks = parseInt(elem.data('minBlocks'), 10) || 0;
        maxBlocks = parseInt(elem.data('maxBlocks'), 10) || 0;

        if (minBlocks > maxBlocks) {
            throw new Blockform.Error('minBlocks must be lower than or equal to maxBlocks');
        }

        this._options = $.extend({}, Blockform.defaults, options);

        this._elem = elem;
        this._elem.bind('blockadd', this._options.addBlock);
        this._elem.bind('blockremove', this._options.removeBlock);

        this._blockIndex = elem.find('[data-role="blockform.blockIndex"]');
        this._blockAdder = elem.find('[data-role="blockform.blockAdder"]');

        this._blockTemplate = elem.find('[data-role="blockform.blockTemplate"]').first();
        this._blockContainer = elem.find('[data-role="blockform.blockContainer"]').first();

        this._minBlocks = minBlocks;
        this._maxBlocks = maxBlocks;

        this._blocks = {};
        this._numBlocks = 0;
        this._free = 0;

        this._loadBlocks();

        this._blockAdder.off('click.blockform');
        this._blockAdder.on('click.blockform', function () {
            that._createBlock();

            return false;
        });

        this._blockContainer.on('click.blockform', '[data-block-remover]', function () {
            var blockId = $(this).closest('[data-block-id]').data('blockId');
            that.removeBlock(blockId);
            return false;
        });
    } // }}}

    /**
     * Add existing blocks to block registry. Blocks are elements with
     * data-block-id attribute.
     */
    Blockform.prototype._loadBlocks = function () { // {{{
        var that = this;

        this._blockContainer.find('> [data-block-id]').each(function () {
            var block = $(this),
                blockId = block.data('blockId'),
                blockNumId = parseInt(blockId, 10);

            that._blocks[blockId] = block;
            that._numBlocks++;

            if (isFinite(blockNumId)) {
                that._free = Math.max(that._free, blockNumId + 1);
            }

            that._trigger('blockadd', block);
        });

        // enforce min-block limit
        if (0 < this._minBlocks && this._numBlocks < this._minBlocks) {
            while (this._numBlocks < this._minBlocks) {
                this._createBlock();
            }
        }

        this._updateIndex();

        // if there is minimum number of blocks, disable removers
        if (this._numBlocks <= this._minBlocks) {
            this._blockContainer.find('[data-role="blockform.blockRemover"]').addClass('disabled');
        }
    }; // }}}

    /**
     * @return {boolean}
     * @throws {Blockform.Error}
     */
    Blockform.prototype._createBlock = function () { // {{{
        if (this._maxBlocks > 0 && this._numBlocks >= this._maxBlocks) {
            return false;
        }

        // pobierz pierwszy wolny numer bloku, utworz nowy blok na bazie
        // templejtu (zastap placeholdery "{{ id }}" numerem bloku), 
        // i jezeli pierwszy element w nowym bloku ma klase .form-block,
        // dodaj go do elementu przechowujacego bloki oraz zaktualizuj
        // indeks
        var blockId = this._free++,
            blockHtml = this._blockTemplate.html(),
            block = $(blockHtml.replace(/\{\{ id \}\}/g, blockId)).first();

        if (block.is('[data-block-id]')) {
            block.css({display: 'none', opacity: 0})
                .appendTo(this._blockContainer)
                .animate({height: 'show', opacity: 1}, function () {
                    // focus on first autofocus element
                    block.find('[autofocus]').first().focus();                
                });

            if (this._numBlocks === this._minBlocks) {
                // ok, time to release block removers
                this._blockContainer.find('[data-role="blockform.blockRemover"]').removeClass('disabled');
            }

            this._blocks[blockId] = block;
            ++this._numBlocks;

            this._updateIndex();

            // if block limit has been reached, disable adder
            if (this._maxBlocks > 0 && this._numBlocks >= this._maxBlocks) {
                this._blockAdder.attr('disabled', true).addClass('disabled');
            }

            this._trigger('blockadd', block);

        } else {
            // niepoprawny templejt, cofnij inkrementacje licznika blokow
            --this._free;
            throw new Blockform.Error('Root element of block template have no data-block-id attribute.');
        }

        return true;
    }; // }}}

    Blockform.prototype._updateIndex = function () { // {{{
        var blocks = this._blocks,
            idx = [],
            id;

        for (id in blocks) {
            if (blocks.hasOwnProperty(id)) {
                idx[idx.length] = id;
            }
        }

        this._blockIndex.val(idx.join(this._options.indexSep));
    }; // }}}

    /**
     * @param {int|string} blockId
     * @return {boolean}
     */
    Blockform.prototype._removeBlock = function (blockId) { // {{{
        var that = this,
            block;

        if (this._numBlocks <= this._minBlocks) {
            return false;
        }

        block = this._blocks[blockId];
        if (block) {
            delete this._blocks[blockId];

            this._numBlocks--;
            this._updateIndex();

            // enable add block functionality
            this._blockAdder.removeAttr('disabled').removeClass('disabled');

            // lock block removers if min blocks reached
            if (this._numBlocks === this._minBlocks) {
                this._blockContainer.find('[data-role="blockform.blockRemover]').addClass('disabled');
            }

            block.animate({paddingTop: 0, paddingBottom: 0, height: 0, opacity: 0}, function () {
                block.remove();
                that._trigger('blockremove', block);
            });

            return true;
        }

        throw new Blockform.Error('Invalid block ID');
    }; // }}}

    /**
     * @param {int|string} blockId
     */
    Blockform.prototype.removeBlock = function (blockId) { // {{{
        return this._removeBlock(blockId);
    }; // }}}

/*
    // przechwyc klikniecie na przyciskach do usuwania blokow
    // i zastap je funkcja do usuwania blokow
    blockContainer.find('> [data-block-id] [data-block-remover]').each(function() {
        // przed usunieciem popros o potwierdzenie
        var removeConfirm = function () {
            if (confirm) {
                confirm.remove();
                confirm = null;
            }
        }

        var removeBlock = function (blockId) {
        return function() {
            if ($(this).hasClass('disabled')) {
                return;
            }

            _removeBlock(blockId);
            return;

            removeConfirm();

            var self = this,
                block = blocks[blockId], // TODO may be undefined
                offset = block.position(),
                yes,
                no,
                z = 0;

            // set zIndex of suggesion list as a maximum of zIndexes encountered
            // on a path from text input to document body
            $(this).parents().each(function() {
                z = Math.max(z, parseFloat($(this).css('zIndex')) || 0);
            });

            yes = $('<div class="yes btn btn-danger"/>')
                .attr('title', 'Remove entry')
                .html('Remove')
                .click(function() {
                    removeConfirm();
                    _removeBlock(blockId);
                });

            no = $('<div class="no btn"/>')
                .attr('title', 'Cancel')
                .html('Cancel')
                .click(removeConfirm);

            confirm = $('<' + block.get(0).tagName + ' class="blockform-confirm" />')
                .append(
                    $('<div class="overlay"/>').css({
                        position: 'absolute',
                        width: '100%',
                        height: '100%'
                    })
                )
                .append(
                    $('<p class="question"/>').text('Please confirm entry removal.').css('position', 'relative')
                )
                .append(
                    $('<div class="buttons"/>').append(yes).append(no).css('position', 'relative')
                )
                .css({
                    position: 'absolute',
                    top:      offset.top,
                    left:     offset.left,
                    zIndex:   z + 1
                })
                .width(block.outerWidth())
                .height(block.outerHeight())
                .insertAfter(block);

            return false;
        };
    }

        var remover = $(this),
            blockId = remover.closest('[data-block-id]').attr('data-block-id'),
            numId = parseInt(blockId);

        if (isFinite(numId)) {
            free = Math.max(free, numId + 1);
        }

        remover.click(removeBlock(blockId));
    });
*/

    /**
     * @param {string} event
     * @param {...*} param
     */
    Blockform.prototype._trigger = function (event) { // {{{
        var params = Array.prototype.slice.call(arguments, 1);
        this._elem.trigger(event, params);
    }; // }}}

    /**
     * @param {string} message
     * @constructor
     */
    Blockform.Error = function (message) { // {{{
        this.name = 'BlockformError';
        this.message = message;
    };
    Blockform.Error.prototype = new Error();
    // }}}

    /**
     * Default settings
     * @namespace
     */
    Blockform.defaults = { // {{{
        indexSep: ','
    }; // }}}

    /**
     * jQuery plugin
     *
     * @param {object} [options]
     * @return jQuery
     */
    $.fn.blockform = function (options) { // {{{
        this.each(function () {
            $(this).data('blockform', new Blockform(this, options));
        });
        return this;
    }; // }}}

    $.fn.blockform.Constructor = Blockform;

    $(function () {
        $('[data-init="blockform"]').blockform();
    });

}(window.jQuery));
