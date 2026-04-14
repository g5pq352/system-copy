/*
Name: 			eCommerce / eCommerce DataTable List - Examples
Written by: 	Okler Themes - (http://www.okler.net)
Theme Version: 	4.0.0
*/

(function($) {

	'use strict';

	/*
	* eCommerce DataTable List
	*/
	var ecommerceListDataTableInit = function() {

		var $ecommerceListTable = $('#datatable-ecommerce-list');

		$ecommerceListTable.dataTable({
			dom: '<"row justify-content-between"<"col-auto"><"col-auto">><"table-responsive"t>ip',
			columnDefs: [
				{
					targets: 0,
					orderable: false
				}
			],
			pageLength: 12,
			order: [],
			language: {
				paginate: {
					previous: '<i class="fas fa-chevron-left"></i>',
					next: '<i class="fas fa-chevron-right"></i>'
				}
			},
			drawCallback: function() {
				
				// Move dataTables info to footer of table
				$ecommerceListTable
					.closest('.dataTables_wrapper')
					.find('.dataTables_info')
					.appendTo( $ecommerceListTable.closest('.datatables-header-footer-wrapper').find('.results-info-wrapper') );

				// Move dataTables pagination to footer of table
				$ecommerceListTable
					.closest('.dataTables_wrapper')
					.find('.dataTables_paginate')
					.appendTo( $ecommerceListTable.closest('.datatables-header-footer-wrapper').find('.pagination-wrapper') );
				
				$ecommerceListTable.closest('.datatables-header-footer-wrapper').find('.pagination').addClass('pagination-modern pagination-modern-spacing justify-content-center');

			}
		});

		// Link "Show" select for change the "pageLength" of dataTable
		$(document).on('change', '.results-per-page', function(){
			var $this = $(this),
				$dataTable = $this.closest('.datatables-header-footer-wrapper').find('.dataTable').DataTable();

			$dataTable.page.len( $this.val() ).draw();
		});

		// Link "Search" field to show results based in the term entered (the "Filter By" is considered to filter the results)
		$(document).on('keyup', '.search-term', function(){
			var $this = $(this),
				$filterBy = $this.closest('.datatables-header-footer-wrapper').find('.filter-by'),
				$dataTableElement = $this.closest('.datatables-header-footer-wrapper').find('.dataTable');

			// 確保 DataTable 已經初始化
			if ( $.fn.DataTable.isDataTable( $dataTableElement ) ) {
				var $dataTable = $dataTableElement.DataTable();

				// 清除所有欄位的搜尋條件
				$dataTable.columns().search('');

				// 執行全域搜尋
				$dataTable.search( $this.val() ).draw();
			}
		});

		// Trigger "keyup" event when "filter-by" changes
		$(document).on('change', '.filter-by', function(){
			var $this = $(this),
				$searchField = $this.closest('.datatables-header-footer-wrapper').find('.search-term');

			$searchField.trigger('keyup');
		});

		// Select All
		$ecommerceListTable.find( '.select-all' ).on('change', function(){
			if( this.checked ) {
				$ecommerceListTable.find( 'input[type="checkbox"]:not(.select-all)' ).prop('checked', true);
			} else {
				$ecommerceListTable.find( 'input[type="checkbox"]:not(.select-all)' ).prop('checked', false);
			}
		})

	};

	ecommerceListDataTableInit();

}(jQuery));