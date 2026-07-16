(function(wp){
  if (!wp || !wp.hooks || !wp.element || !wp.components || !wp.blockEditor || !wp.serverSideRender) return;
  const el = wp.element.createElement;
  const InspectorControls = wp.blockEditor.InspectorControls;
  const PanelBody = wp.components.PanelBody;
  const SelectControl = wp.components.SelectControl;
  const TextControl = wp.components.TextControl;
  const ToggleControl = wp.components.ToggleControl;
  const ServerSideRender = wp.serverSideRender;
  const suiteBlocks = ['asm-suite/adoptables','asm-suite/adopted','asm-suite/statistics','asm-suite/featured-animal','asm-suite/adoption-form'];
  function Controls(BlockEdit){
    return function(props){
      if (suiteBlocks.indexOf(props.name) === -1) return el(BlockEdit, props);
      const attrs = props.attributes || {};
      const set = props.setAttributes;
      return el(wp.element.Fragment, {},
        el(InspectorControls, {}, el(PanelBody, { title: 'ASM Suite preview controls', initialOpen: true },
          el(SelectControl, { label: 'Source', value: attrs.source || '', options: [
            {label:'Plugin default', value:''},{label:'ASM', value:'asm'},{label:'Custom API', value:'custom_api'},{label:'Shelterluv', value:'shelterluv'},{label:'PetPoint', value:'petpoint'}
          ], onChange: function(value){ set({source:value}); } }),
          el(SelectControl, { label: 'Layout', value: attrs.layout || '', options: [
            {label:'Default', value:''},{label:'Grid', value:'grid'},{label:'List', value:'list'},{label:'Compact', value:'compact'},{label:'Featured', value:'featured'}
          ], onChange: function(value){ set({layout:value}); } }),
          el(ToggleControl, { label: 'Show filters', checked: attrs.filters !== false, onChange: function(value){ set({filters:!!value}); } }),
          el(TextControl, { label: 'Style preset/class', value: attrs.style || '', onChange: function(value){ set({style:value}); } })
        )),
        el('div', { className: 'asm-suite-editor-live-preview' },
          el(ServerSideRender, { block: props.name, attributes: attrs })
        )
      );
    };
  }
  wp.hooks.addFilter('editor.BlockEdit', 'asm-suite/live-preview-controls', Controls);
})(window.wp);
