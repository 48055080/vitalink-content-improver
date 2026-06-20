/**
 * Vitalink Content Improver — Gutenberg sidebar.
 *
 * The compiled bundle exposes four actions (Improve, Summarize, Translate,
 * Alt Text) and routes each through the Vitalink REST namespace. The PHP
 * side handles the actual provider call and caching.
 *
 * @package Vitalink\ContentImprover
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editPost ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { PanelBody, Button, SelectControl, TextareaControl, Spinner, Notice } = wp.components;
	const { useState } = wp.element;
	const { useSelect, useDispatch } = wp.data;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	const { VitalinkCi } = window;

	function callFeature( path, body ) {
		return apiFetch( {
			path: `${ VitalinkCi.restNamespace }/${ path }`,
			method: 'POST',
			data: body,
		} );
	}

	function Sidebar() {
		const [ style, setStyle ] = useState( 'clearer' );
		const [ length, setLength ] = useState( 150 );
		const [ target, setTarget ] = useState( 'English' );
		const [ busy, setBusy ] = useState( false );
		const [ error, setError ] = useState( null );
		const [ notice, setNotice ] = useState( null );

		const { editPost } = useDispatch( 'core/editor' );
		const current = useSelect( ( select ) => {
			const sel = select( 'core/editor' );
			return {
				title: sel.getEditedPostAttribute( 'title' ),
				content: sel.getEditedPostAttribute( 'content' ),
			};
		}, [] );

		function extractSelectedOrAll() {
			// For v0.1, we operate on the entire post content. Selection-based
			// editing is on the roadmap (requires block selection API).
			return stripBlocks( current.content || '' );
		}

		function stripBlocks( html ) {
			const tmp = document.createElement( 'div' );
			tmp.innerHTML = html;
			return ( tmp.textContent || tmp.innerText || '' ).trim();
		}

		async function improve() {
			setBusy( true );
			setError( null );
			setNotice( null );
			try {
				const text = extractSelectedOrAll();
				if ( ! text ) {
					setError( __( 'Post is empty.', 'vitalink-content-improver' ) );
					return;
				}
				const res = await callFeature( 'improve', { text, style } );
				editPost( { content: res.text } );
				setNotice( __( 'Content improved.', 'vitalink-content-improver' ) );
			} catch ( e ) {
				setError( e.message || __( 'Improve failed.', 'vitalink-content-improver' ) );
			} finally {
				setBusy( false );
			}
		}

		async function summarize() {
			setBusy( true );
			setError( null );
			setNotice( null );
			try {
				const text = extractSelectedOrAll();
				if ( ! text ) {
					setError( __( 'Post is empty.', 'vitalink-content-improver' ) );
					return;
				}
				const res = await callFeature( 'summarize', { text, length } );
				setNotice( res.text );
			} catch ( e ) {
				setError( e.message || __( 'Summarize failed.', 'vitalink-content-improver' ) );
			} finally {
				setBusy( false );
			}
		}

		async function translate() {
			setBusy( true );
			setError( null );
			setNotice( null );
			try {
				const text = extractSelectedOrAll();
				if ( ! text ) {
					setError( __( 'Post is empty.', 'vitalink-content-improver' ) );
					return;
				}
				const res = await callFeature( 'translate', { text, target } );
				setNotice( res.text );
			} catch ( e ) {
				setError( e.message || __( 'Translate failed.', 'vitalink-content-improver' ) );
			} finally {
				setBusy( false );
			}
		}

		return (
			<>
				<PluginSidebarMoreMenuItem target="vitalink-ci-sidebar">
					{ __( 'Vitalink', 'vitalink-content-improver' ) }
				</PluginSidebarMoreMenuItem>
				<PluginSidebar name="vitalink-ci-sidebar" title={ __( 'Vitalink', 'vitalink-content-improver' ) }>
					<PanelBody title={ __( 'Improve', 'vitalink-content-improver' ) } initialOpen={ true }>
						<SelectControl
							label={ __( 'Style', 'vitalink-content-improver' ) }
							value={ style }
							options={ [
								{ label: __( 'Clearer', 'vitalink-content-improver' ), value: 'clearer' },
								{ label: __( 'Shorter', 'vitalink-content-improver' ), value: 'shorter' },
								{ label: __( 'More formal', 'vitalink-content-improver' ), value: 'more_formal' },
							] }
							onChange={ setStyle }
						/>
						<Button variant="primary" onClick={ improve } disabled={ busy }>
							{ busy ? <Spinner /> : __( 'Improve post', 'vitalink-content-improver' ) }
						</Button>
					</PanelBody>
					<PanelBody title={ __( 'Summarize', 'vitalink-content-improver' ) }>
						<SelectControl
							label={ __( 'Length', 'vitalink-content-improver' ) }
							value={ String( length ) }
							options={ [
								{ label: '~50 words', value: '50' },
								{ label: '~150 words', value: '150' },
								{ label: '~300 words', value: '300' },
							] }
							onChange={ ( v ) => setLength( parseInt( v, 10 ) ) }
						/>
						<Button variant="secondary" onClick={ summarize } disabled={ busy }>
							{ __( 'Summarize', 'vitalink-content-improver' ) }
						</Button>
					</PanelBody>
					<PanelBody title={ __( 'Translate', 'vitalink-content-improver' ) }>
						<TextareaControl
							label={ __( 'Target language', 'vitalink-content-improver' ) }
							value={ target }
							onChange={ setTarget }
							rows={ 1 }
						/>
						<Button variant="secondary" onClick={ translate } disabled={ busy }>
							{ __( 'Translate', 'vitalink-content-improver' ) }
						</Button>
					</PanelBody>
					{ error && <Notice status="error" isDismissible={ true } onRemove={ () => setError( null ) }>{ error }</Notice> }
					{ notice && <Notice status="success" isDismissible={ true } onRemove={ () => setNotice( null ) }><pre style={ { whiteSpace: 'pre-wrap' } }>{ notice }</pre></Notice> }
				</PluginSidebar>
			</>
		);
	}

	registerPlugin( 'vitalink-ci', { render: Sidebar, icon: null } );
} )( window.wp );
