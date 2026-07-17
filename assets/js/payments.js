(function () {
  'use strict';

  function toNumber(value) {
    var number = parseFloat(value);
    return Number.isFinite(number) ? number : NaN;
  }

  function formatType(type) {
    return type === 'recurring' ? 'Monthly' : 'One-off';
  }

  function init(widget) {
    var typeButtons = Array.prototype.slice.call(widget.querySelectorAll('[data-payment-type]'));
    var sections = Array.prototype.slice.call(widget.querySelectorAll('[data-payment-section]'));
    var impact = widget.querySelector('[data-payment-impact]');
    var error = widget.querySelector('[data-payment-error]');
    var continueButton = widget.querySelector('[data-payment-continue]');
    var feeRecovery = widget.querySelector('[data-payment-fee-recovery]');
    var total = widget.querySelector('[data-payment-total]');
    var currentType = widget.dataset.defaultType === 'recurring' ? 'recurring' : 'one_off';
    var selected = {
      one_off: { amount: '', source: '', description: '' },
      recurring: { amount: '', source: '', description: '' }
    };

    function sectionFor(type) {
      return widget.querySelector('[data-payment-section="' + type + '"]');
    }

    function selectedState() {
      return selected[currentType];
    }

    function amountIsValid(type, amount) {
      var section = sectionFor(type);
      var value = toNumber(amount);
      var min = section ? toNumber(section.dataset.minAmount) : 0;
      var max = section ? toNumber(section.dataset.maxAmount) : Infinity;

      if (!Number.isFinite(value)) return false;
      if (value <= 0) return false;
      if (Number.isFinite(min) && value < min) return false;
      if (Number.isFinite(max) && value > max) return false;
      return true;
    }

    function validationMessage(type, amount) {
      var section = sectionFor(type);
      var value = toNumber(amount);
      var min = section ? toNumber(section.dataset.minAmount) : 0;
      var max = section ? toNumber(section.dataset.maxAmount) : Infinity;

      if (!amount) return '';
      if (!Number.isFinite(value) || value <= 0) return 'Enter a valid amount.';
      if (Number.isFinite(min) && value < min) return 'Minimum ' + formatType(type).toLowerCase() + ' amount is ' + section.dataset.minLabel + '.';
      if (Number.isFinite(max) && value > max) return 'Maximum ' + formatType(type).toLowerCase() + ' amount is ' + section.dataset.maxLabel + '.';
      return '';
    }

    function amountWithFees(amount) {
      var value = toNumber(amount);
      var rate = toNumber(widget.dataset.feeRate || '0');
      var fixed = toNumber(widget.dataset.feeFixed || '0');
      if (!Number.isFinite(value)) return NaN;
      if (!feeRecovery || !feeRecovery.checked) return value;
      rate = Number.isFinite(rate) ? Math.max(0, Math.min(0.3, rate)) : 0;
      fixed = Number.isFinite(fixed) ? Math.max(0, fixed) : 0;
      return Math.round(((value + fixed) / (1 - rate)) * 100) / 100;
    }

    function formatMoney(amount) {
      try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: widget.dataset.currency || 'GBP' }).format(amount);
      } catch (error) {
        return (widget.dataset.currency || '') + ' ' + amount.toFixed(2);
      }
    }

    function setActivePreset(section, activeButton) {
      Array.prototype.slice.call(section.querySelectorAll('[data-payment-amount]')).forEach(function (button) {
        var isActive = button === activeButton;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
    }

    function update() {
      typeButtons.forEach(function (button) {
        var active = button.dataset.paymentType === currentType;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
        button.setAttribute('tabindex', active ? '0' : '-1');
      });

      sections.forEach(function (section) {
        section.hidden = section.dataset.paymentSection !== currentType;
      });

      var state = selectedState();
      var message = validationMessage(currentType, state.amount);
      var valid = amountIsValid(currentType, state.amount);

      if (impact) impact.textContent = state.description || '';
      if (error) error.textContent = message;
      if (continueButton) continueButton.disabled = !valid;
      if (total) total.textContent = valid ? 'Total: ' + formatMoney(amountWithFees(state.amount)) : '';
    }

    function selectType(type) {
      if (!sectionFor(type)) return;
      currentType = type;
      update();
    }

    typeButtons.forEach(function (button, index) {
      button.addEventListener('click', function () {
        selectType(button.dataset.paymentType);
      });

      button.addEventListener('keydown', function (event) {
        var nextIndex;
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
          event.preventDefault();
          nextIndex = (index + 1) % typeButtons.length;
          typeButtons[nextIndex].focus();
          selectType(typeButtons[nextIndex].dataset.paymentType);
        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
          event.preventDefault();
          nextIndex = (index - 1 + typeButtons.length) % typeButtons.length;
          typeButtons[nextIndex].focus();
          selectType(typeButtons[nextIndex].dataset.paymentType);
        }
      });
    });

    sections.forEach(function (section) {
      var type = section.dataset.paymentSection;
      var customInput = section.querySelector('[data-payment-custom]');

      Array.prototype.slice.call(section.querySelectorAll('[data-payment-amount]')).forEach(function (button) {
        button.addEventListener('click', function () {
          selected[type] = {
            amount: button.dataset.paymentAmount || '',
            source: 'preset',
            description: button.dataset.paymentDescription || ''
          };
          setActivePreset(section, button);
          if (customInput) customInput.value = '';
          update();
        });
      });

      if (customInput) {
        customInput.addEventListener('input', function () {
          selected[type] = {
            amount: customInput.value,
            source: 'custom',
            description: customInput.value ? customInput.dataset.paymentDescription || '' : ''
          };
          setActivePreset(section, null);
          update();
        });
      }
    });

    if (feeRecovery) feeRecovery.addEventListener('change', update);

    if (continueButton) {
      continueButton.addEventListener('click', function () {
        var state = selectedState();
        if (!amountIsValid(currentType, state.amount)) {
          update();
          return;
        }

        if (!window.PluginPayments || !window.PluginPayments.ajaxUrl || !window.PluginPayments.nonce) {
          console.log('Donation widget selection', {
            type: currentType,
            amount: toNumber(state.amount),
            source: state.source,
            currency: widget.dataset.currency || '',
            provider: widget.dataset.provider || ''
          });
          return;
        }

        continueButton.disabled = true;
        var formData = new FormData();
        formData.append('action', 'plugin_payments_checkout');
        formData.append('nonce', window.PluginPayments.nonce);
        formData.append('type', currentType);
        formData.append('amount', state.amount);
        formData.append('campaign', widget.dataset.campaign || '');
        formData.append('gift_aid', widget.querySelector('[data-payment-gift-aid]') && widget.querySelector('[data-payment-gift-aid]').checked ? '1' : '0');
        formData.append('fee_recovery', feeRecovery && feeRecovery.checked ? '1' : '0');

        fetch(window.PluginPayments.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        }).then(function (response) {
          return response.json();
        }).then(function (json) {
          if (json && json.success && json.data && json.data.url) {
            window.location.href = json.data.url;
            return;
          }
          if (error) error.textContent = (json && json.data && json.data.message) ? json.data.message : 'Checkout could not be started.';
          update();
        }).catch(function () {
          if (error) error.textContent = 'Checkout could not be started. Please try again.';
          update();
        });
      });
    }

    update();
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.slice.call(document.querySelectorAll('.plugin-payments-widget')).forEach(init);
  });
}());
