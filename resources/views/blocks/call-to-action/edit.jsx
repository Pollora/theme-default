import { useBlockProps, RichText, InspectorControls, URLInput } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
    const { heading, description, buttonText, buttonUrl, alignment } = attributes;
    const blockProps = useBlockProps({ className: `has-text-align-${alignment}` });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Button Settings', '%theme_name%')}>
                    <TextControl
                        label={__('Button Text', '%theme_name%')}
                        value={buttonText}
                        onChange={(val) => setAttributes({ buttonText: val })}
                    />
                    <URLInput
                        label={__('Button URL', '%theme_name%')}
                        value={buttonUrl}
                        onChange={(val) => setAttributes({ buttonUrl: val })}
                    />
                </PanelBody>
                <PanelBody title={__('Layout', '%theme_name%')}>
                    <SelectControl
                        label={__('Text Alignment', '%theme_name%')}
                        value={alignment}
                        options={[
                            { label: __('Left', '%theme_name%'), value: 'left' },
                            { label: __('Center', '%theme_name%'), value: 'center' },
                            { label: __('Right', '%theme_name%'), value: 'right' },
                        ]}
                        onChange={(val) => setAttributes({ alignment: val })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <RichText
                    tagName="h2"
                    className="wp-block-%theme_name%-call-to-action__heading"
                    value={heading}
                    onChange={(val) => setAttributes({ heading: val })}
                    placeholder={__('Your heading here…', '%theme_name%')}
                />
                <RichText
                    tagName="p"
                    className="wp-block-%theme_name%-call-to-action__description"
                    value={description}
                    onChange={(val) => setAttributes({ description: val })}
                    placeholder={__('Add a description…', '%theme_name%')}
                />
                <div className="wp-block-%theme_name%-call-to-action__button-wrapper">
                    <RichText
                        tagName="span"
                        className="wp-block-%theme_name%-call-to-action__button"
                        value={buttonText}
                        onChange={(val) => setAttributes({ buttonText: val })}
                        placeholder={__('Button text', '%theme_name%')}
                    />
                </div>
            </div>
        </>
    );
}
