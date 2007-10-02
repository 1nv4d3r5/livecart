if (Backend == undefined)
{
	var Backend = {}
}

Backend.Category = {

	/**
	 * category tab controll instance
	 */
	tabControl: null,

	/**
	 * Category tree browser instance
	 */
	treeBrowser: null,
    
	/**
	 * Id of currenty selected category. Used for category tab content switching
	 */
	activeCategoryId: null,

	/**
	 * Category module initialization
	 */
	init: function()
	{
		this.initCategoryBrowser();
        this.initTabs();
		this.initTreeControls();
	},

	initPage: function()
	{
		// check for bookmark
		if (window.location.hash.length == 0)
        {
            window.location.hash = '#cat_1#tabProducts__';
			Backend.Breadcrumb.display(1);
        }

		Backend.Category.treeBrowser.showFeedback = 
			function(itemId) 
			{
				if (!this.iconUrls)
				{
					this.iconUrls = new Object();	
				}
				
				if (!this.iconUrls[itemId])
				{
                    this.iconUrls[itemId] = this.getItemImage(itemId, 0, 0);
                    var img = this._globalIdStorageFind(itemId).htmlNode.down('img', 2);
                    img.originalSrc = img.src;
    				img.src = 'image/indicator.gif';                    
                }
			}
		
		Backend.Category.treeBrowser.hideFeedback = 
			function(itemId)
			{
                if (null != this.iconUrls[itemId])
                {
        			this.iconUrls[itemId] = this.getItemImage(itemId, 0, 0);
                    var img = this._globalIdStorageFind(itemId).htmlNode.down('img', 2);
                    img.src = img.originalSrc;
                    this.iconUrls[itemId] = null;                            
                }
			}
			
		var elements = window.location.hash.split('#');
		if (elements[1].substr(0, 4) == 'cat_')
		{
			var parts = elements[1].split('_');
			var categoryId = parts[1];
            
            Backend.Category.activeCategoryId = categoryId;
			Backend.Category.treeBrowser.selectItem(categoryId, false, false);
            
			return true;		  
		}

        if($('categoryBrowser').getElementsByClassName('selectedTreeRow')[0])
        {
    		var treeNode = $('categoryBrowser').getElementsByClassName('selectedTreeRow')[0].parentNode;
    		treeNode.onclick();	
    		Backend.ajaxNav.add('cat_' + treeNode.parentObject.id + '#tabProducts');
        }
	},
    
    initTreeControls: function() 
    {
        if($("categoryBrowserActions"))
        {
            Event.observe($("createNewCategoryLink"), "click", function(e) {
                Event.stop(e);
                Backend.Category.createNewBranch(); 
            }.bind(this));
            
            Event.observe($("removeCategoryLink"), "click", function(e) {
                Event.stop(e);
                if (confirm(Backend.Category.messages._confirm_category_remove)) 
                {
                    Backend.Category.removeBranch(); 
                }
            }.bind(this));
            
            Event.observe($("moveCategoryUp"), "click", function(e) {
                Event.stop(e);
                this.moveCategory(Backend.Category.activeCategoryId, 'up_strict');
            }.bind(this));
            
            Event.observe($("moveCategoryDown"), "click", function(e) {
                Event.stop(e);
                this.moveCategory(Backend.Category.activeCategoryId, 'down_strict');
            }.bind(this));
        }
    },

	/**
	 * Builds category tree browser object (dhtmlxTree) and initializes its params
	 */
	initCategoryBrowser: function()
	{
		this.treeBrowser = new dhtmlXTreeObject("categoryBrowser","","", 0);

		Backend.Category.treeBrowser.setCategoryStyle = 
			function(category)
			{		
                this.setItemColor(category.ID, (category.isEnabled < 1 ? '#999' : '#000'), (category.isEnabled < 1 ? '#999' : '#fff'));
            }

		Backend.Breadcrumb.setTree(this.treeBrowser);
		
		this.treeBrowser.setImagePath("image/backend/dhtmlxtree/");
		this.treeBrowser.setOnClickHandler(this.activateCategory);
        if(Backend.Category.allowSorting)
        {
		    this.treeBrowser.setDragHandler(this.reorderCategory);
            this.treeBrowser.enableDragAndDrop(1);
		}
	},

	initTabs: function()
	{
		this.tabControl = new CategoryTabControl(this.treeBrowser, 'tabList', 'sectionContainer', 'image/indicator.gif');
	},

    showControls: function()
    {
        // popup window
		if (!$("removeCategoryLink"))
        {
			return false;
		}
		
		var categoryId = Backend.Category.treeBrowser.getSelectedItemId();
        
        if(categoryId == '1') 
        {
            $("removeCategoryLink").parentNode.hide();
            $("moveCategoryUp").parentNode.hide();
            $("moveCategoryDown").parentNode.hide();
        }
        else
        {
            $("removeCategoryLink").parentNode.show();
            
            parentId = Backend.Category.treeBrowser.getParentId(categoryId)
            categoryIndex = Backend.Category.treeBrowser.getIndexById(categoryId)
			if(parentId)
			{
	            nextCategoryId = Backend.Category.treeBrowser.getChildItemIdByIndex(parentId, parseInt(categoryIndex) + 1)
	
	            if(nextCategoryId) $("moveCategoryDown").parentNode.show();
	            else $("moveCategoryDown").parentNode.hide();
	            
	            if(categoryIndex > 0) $("moveCategoryUp").parentNode.show();
	            else $("moveCategoryUp").parentNode.hide();
			}
        }  
    },

	/**
	 * Tree browser onClick handler. Activates selected category by realoading active
	 * tab with category specific data
	 *
	 * @todo Find some better way to reference/retrieve the DOM nodes from tree by category ID's 
	 * (automatically assign ID's somehow?). Also necessary for bookmarking (the ID's have to be preassigned).
	 */
	activateCategory: function(categoryId)
	{
        Backend.Product.hideAddForm();
        Backend.Breadcrumb.display(categoryId);
  
        Backend.Category.showControls();

		if(Backend.Product) Backend.Product.Editor.prototype.showCategoriesContainer();

		Backend.Category.tabControl.switchCategory(categoryId, Backend.Category.activeCategoryId);
		Backend.Category.activeCategoryId = categoryId;

		// set ID for the current tree node element
		$('categoryBrowser').getElementsByClassName('selectedTreeRow')[0].parentNode.id = 'cat_' + categoryId;
			
		// and register browser history event to enable backwar/forward navigation
		// Backend.ajaxNav.add('cat_' + categoryId);
		if(Backend.Category.tabControl.activeTab) Backend.Category.tabControl.activeTab.onclick();

        var currentProductId = Backend.Product.Editor.prototype.getCurrentProductId();
        if(currentProductId)
        {
            Backend.Product.Editor.prototype.getInstance(currentProductId, false).removeTinyMce();
        }
	},

	createNewBranch: function()
	{
		new LiveCart.AjaxRequest(
			this.getUrlForNewNode(this.treeBrowser.getSelectedItemId()), 
            false, 
            function(response) { this.afterNewBranchCreated(response) }.bind(this)
		);
	},

    moveCategory: function(categoryID, direction)
    {
        if('up_strict' == direction)
        {
            Backend.Category.treeBrowser._reorderDirection = 'left';
        }
        else
        {
            Backend.Category.treeBrowser._reorderDirection = 'right';
        }
        
        Backend.Category.treeBrowser.moveItem(categoryID, direction);
        Backend.Category.treeBrowser._reorderDirection = false;
        Backend.Category.showControls();
        
        return;

    },

	afterNewBranchCreated: function(response)
	{
        var newCategory = eval('(' + response.responseText + ')');
        var parentCategoryId = Backend.Category.treeBrowser.getSelectedItemId();

		if(Backend.Category.treeBrowser.hasChildren(parentCategoryId) === true)
		{
            Backend.Category.treeBrowser.openItem(parentCategoryId);
			
			this.createNewCatInterval = setInterval(function()
			{
			    if(Backend.Category.treeBrowser.getIndexById(newCategory.ID) !== null)
				{
                    Backend.Category.treeBrowser.selectItem(newCategory.ID, true);
                    this.tabControl.activateTab("tabMainDetails", newCategory.ID);
                    
                    Backend.Breadcrumb.display(newCategory.ID);  
                    Backend.Category.showControls();
                    
					clearInterval(this.createNewCatInterval);
			    }
			}.bind(this)
			, 200);
		}
		else
		{
			 this.treeBrowser.insertNewItem(parentCategoryId, newCategory.ID, newCategory.name, 0, 0, 0, 0, 'SELECT');
		     this.tabControl.activateTab("tabMainDetails", newCategory.ID);

            Backend.Breadcrumb.display(newCategory.ID);  
            Backend.Category.showControls();

		}
		Backend.Category.treeBrowser.setCategoryStyle(newCategory);
            
	},

	/**
	 * Updating category branch via ajax request
	 */
	updateBranch: function(formObj)
	{
		new LiveCart.AjaxRequest(formObj, null, this.afterBranchUpdate.bind(this));
	},

	/**
	 * Post-processing request
	 */
	afterBranchUpdate: function(response)
	{
		var categoryData = eval('(' + response.responseText + ')');

		Backend.Category.treeBrowser.setItemText(categoryData.ID, categoryData.name);
		Backend.Category.treeBrowser.setCategoryStyle(categoryData);
	},

	/**
	 * Gets an URL for creating a new node (uses a globaly defined variable "newNodeUrl")
	 */
	getUrlForNewNode: function(parentNodeId)
	{
        return this.buildUrl(this.links.create, parentNodeId);
	},
    
	getUrlItemsInTabsCount: function(categoryId)
	{
        return this.buildUrl(Backend.Category.links.countTabsItems, categoryId);
	},

	getUrlForNodeRemoval: function(nodeId)
	{
		return this.buildUrl(this.links.remove, nodeId);
	},

    getUrlForNodeReorder: function(id, pid, direction) 
    {
		direction = direction || '';
        return Backend.Category.links.reorder
                .replace('_id_', id)
                .replace('_pid_', pid)
                .replace('_direction_', direction);
    },

	buildUrl: function(urlPattern, id)
	{
		return urlPattern.replace('_id_', id);
	},

	/**
	 * Removes a selected category (including sub-trees) from a store
	 */
	removeBranch: function()
	{
		var nodeIdToRemove = this.treeBrowser.getSelectedItemId();
		var parentNodeId = this.treeBrowser.getParentId(nodeIdToRemove);

		new LiveCart.AjaxRequest(this.getUrlForNodeRemoval(nodeIdToRemove));

		this.treeBrowser.deleteItem(nodeIdToRemove, true);
		this.activateCategory(parentNodeId);
	},

	reorderCategory: function(targetId, parentId, siblingNodeId)
	{
		if(!parentId) parentId = 1;
        new LiveCart.AjaxRequest(Backend.Category.getUrlForNodeReorder(targetId, parentId, Backend.Category.treeBrowser._reorderDirection));
	
        return true;
    },
    
    /**
     * Insert array of categories into tree
     * 
     * @param array categories Array of category objects. Every category object should contain these elements
     *     parent - Id of parent category
     *     ID - Id o category
     *     name - Category name in current language
     *     options - Advanced options
     *     childrenCount - Indicates that this node has N childs
     */
    addCategories: function(categories) 
    {
        $A(categories).each(function(category) {         
            if(!category.parent || 0 == category.parent) 
            {
                category.options = "";
                category.parent = 0;
            }
            else if(!category.option) 
            {
                category.options = "";
            }

            Backend.Category.treeBrowser.insertNewItem(category.parent,category.ID,category.name, null, 0, 0, 0, category.options, !category.childrenCount ? 0 : category.childrenCount);
            Backend.Category.treeBrowser.setCategoryStyle(category);
        });
    },
        
    loadBookmarkedCategory: function(categoryID) 
    {       
        var match = window.location.hash.match(/cat_(\d+)/);
        if(match) 
        {
            var alreadyLoaded = false;
            try
            {
                $A(Backend.Category.treeBrowser._globalIdStorage).each(function(id) 
                {
                    if(id == match[1]) 
					{
					   alreadyLoaded = true;
					   throw $break;
				    }
                });
            }
            catch(e) { }
			
            if(!alreadyLoaded) 
            {
                Backend.Category.treeBrowser.loadXML(Backend.Category.links.categoryRecursiveAutoloading + "?id=" + match[1]);
            }
        }
    },
    
    loadBookmarkedProduct: function()
    {
        var productID = window.location.hash.match(/product_(\d+)/);
        if (productID && productID[1])
        {
            Element.show($('loadingProduct'));
            Backend.Product.openProduct(productID[1], null, function() { Element.hide($('loadingProduct')); });
        }        
    }
}



var CategoryTabControl = Class.create();

/**
 * Category manager tab control
 */
CategoryTabControl.prototype = {

    tabItemsCounts: { },
	activeTab: null,
	indicatorImageName: null,
	treeBrowser: null,
	sectionContainerName: null,

	initialize: function(treeBrowser, tabContainerName, sectionContainerName, indicatorImageName)
	{
		this.treeBrowser = treeBrowser;
        Backend.Breadcrumb.setTree(this.treeBrowser);
		
		this.sectionContainerName = sectionContainerName;

		if (indicatorImageName != undefined)
		{
			this.indicatorImageName = indicatorImageName;
		}

		var tabList = document.getElementsByClassName("tab");
		for (var i = 0; i < tabList.length; i++)
		{
			tabList[i].onclick = this.handleTabClick;
			tabList[i].onmouseover = this.handleTabMouseOver.bindAsEventListener(this);
			tabList[i].onmouseout = this.handleTabMouseOut.bindAsEventListener(this);

			tabList[i].tabControl = this;			
			
			aElementList = tabList[i].getElementsByTagName('a');
			if (aElementList.length > 0)
			{
				// Getting an URL pattern that tab is pointing to by analysing "<A>" element
				tabList[i].url = aElementList[0].href;
				new Insertion.After(aElementList[0], aElementList[0].innerHTML);
				// inserting indicator element which will be show on tab activation
				new Insertion.Before(aElementList[0], '<img src="' + this.indicatorImageName + '" class="tabIndicator" id="' + tabList[i].id + 'Indicator" alt="Tab indicator" style="display:none"/> ');
				Element.remove(aElementList[0]);
			}

			if (tabList[i].id == '')
			{
				tabList[i].id = 'tab' + i;
			}
			if (Element.hasClassName(tabList[i], 'active'))
			{
				this.activeTab = tabList[i];
				var containerId = this.getContainerId(tabList[i].id, treeBrowser.getSelectedItemId());
				if ($(containerId) != undefined)
				{
					Element.show(containerId);
				}
			}
			else
			{
				//Element.hide(this.getContainerId(tabList[i].id, treeBrowser.getSelectedItemId()));
			}
		}
		
		// register for AJAX browser navigation handler
		//this.activeTab.onclick();
	},

	handleTabMouseOver: function(evt)
	{
		var target = "";
		if (evt.target == undefined)
		{
			target = evt.srcElement;
		}
		else
		{
			target = evt.target;
		}
		if (this.activeTab != target)
		{
			Element.removeClassName(target, 'inactive');
			Element.addClassName(target, 'hover');
		}
	},

	handleTabMouseOut: function(evt)
	{
		var target = "";
		if (evt.target == undefined)
		{
			target = evt.srcElement;
		}
		else
		{
			target = evt.target;
		}

		if (this.activeTab != target)
		{
			Element.removeClassName(target, 'hover');
			Element.addClassName(target, 'inactive');
		}
	},

	/**
	 * Tab click event handler (performs tab styling and content activation)
	 */
	handleTabClick: function()
	{
		this.tabControl.activateTab(this);
		
		Backend.ajaxNav.add('cat_' + Backend.Category.activeCategoryId + '#' + this.id, this.id);		
	},

	/**
	 * Activates a given tab of currenty selected category
	 */
	activateTab: function(targetTab, categoryIdToActivate)
	{
		targetTab = $(targetTab);
		
        var tab = targetTab;
        var id = categoryIdToActivate;
        
		var categoryId = (categoryIdToActivate == undefined ? this.treeBrowser.getSelectedItemId() : categoryIdToActivate);
        this.updateTabItemsCount(categoryId);

        $(this.sectionContainerName).childElements().invoke("hide");


		// get help context
		var helpContext = document.getElementsByClassName('tabHelp', targetTab);
		if (helpContext.length > 0)
		{
			Backend.setHelpContext(helpContext[0].firstChild.nodeValue);
		}
		        
		var tabId = targetTab.id;

		if (this.activeTab == targetTab)
		{
			var containerId = this.getContainerId(targetTab.id, categoryId)
			if ($(containerId) != undefined)
			{
				if (!Element.empty(containerId))
				{
					Element.show(this.getContainerId(targetTab.id, categoryId));
					return;
				}
			}
		}

		if (this.activeTab != null)
		{
			Element.removeClassName(this.activeTab, 'active');
			Element.addClassName(this.activeTab, 'inactive');
			var activeContainerId = this.getContainerId(this.activeTab.id, categoryId);
			if ($(activeContainerId) != undefined)
			{
				Element.hide(activeContainerId);
			}
		}

		this.activeTab = targetTab;
		Element.removeClassName(this.activeTab, 'hover');
		Element.addClassName(this.activeTab, 'active');

		this.loadTabContent(tabId, categoryId);
		
		$(this.sectionContainerName).immediateDescendants().each(function(container)
		{
			container.hide();
		});
		
		Element.show(this.getContainerId(this.activeTab.id, categoryId));
	},

	loadTabContent: function(tabId, categoryId)
	{
        var containerId = this.getContainerId(tabId, categoryId);

		if ($(containerId) == undefined)
		{
			new Insertion.Bottom(this.sectionContainerName, '<div class="' + tabId + '" id="' + containerId + '"></div>');
		}
		if (categoryId != "" && Element.empty(containerId))
		{
            Backend.Category.treeBrowser.showFeedback(parseInt(categoryId));
			new LiveCart.AjaxUpdater(this.getTabUrl(tabId, categoryId),
									 this.getContainerId(tabId, categoryId),
									 this.getIndicatorId(tabId),
                                     undefined,
                                     function(){ 
									   Backend.Category.treeBrowser.hideFeedback(categoryId); 
									   if ('tabMainDetails' == tabId)
									   {
                                          var nameField = $(containerId).down('form').elements.namedItem('name');
                                          if ('New Category ' == nameField.value.substr(0, 13))
                                          {
                                            nameField.select();
                                            nameField.focus();
                                          }
                                       }                                       
									 }
                                     );
		}
	},

	getIndicatorId: function(tabName)
	{
		return tabName + 'Indicator';
	},

	getContainerId: function(tabName, categoryId)
	{
		return tabName + 'Content_' + categoryId;
	},

	getTabUrl: function(tabName, categoryId)
	{
		var url = $(tabName).url.replace('_id_', categoryId);
		return url;
	},

	/**
	 * Reset content related to a given tab. When tab will be activated content must
	 * be resent
	 */
	resetContent: function(tabObj, categoryId)
	{
		var contentContainerId = this.getContainerId(tabObj.id, categoryId);
		if ($(contentContainerId) != undefined)
		{
			$(contentContainerId).innerHTML = '';
			Element.hide(contentContainerId);
		}
	},

	reloadActiveTab: function()
	{
		categoryId = this.treeBrowser.getSelectedItemId();
		this.resetContent(this.activeTab, categoryId);
		this.activateTab(this.activeTab, categoryId);
	},

	switchCategory: function(currentCategory, previousActiveCategoryId)
	{
		if (previousActiveCategoryId != null && this.activeTab)
		{
			var prevContainer = this.getContainerId(this.activeTab.id, previousActiveCategoryId);
			if ($(prevContainer) != undefined)
			{
                Element.hide(prevContainer);
			}
		}
	},
	
    loadCategoryTabsCount: function(categories) 
    {
        $H(categories).each(function(category) {         
            CategoryTabControl.prototype.tabItemsCounts[category.key] = category.value;
        });
    },
    
    updateTabItemsCount: function(categoryID)
    {     
        if(categoryID != "" && !CategoryTabControl.prototype.tabItemsCounts[categoryID])
        {
            new LiveCart.AjaxRequest(
                Backend.Category.getUrlItemsInTabsCount(categoryID), 
        		false,
        		function(response) 
                { 
                    CategoryTabControl.prototype.tabItemsCounts[categoryID] = eval("(" + response.responseText + ")");
                    CategoryTabControl.prototype.setTabItemsCount(categoryID); 
                }
            );
        } 
        else 
        {
            CategoryTabControl.prototype.setTabItemsCount(categoryID); 
        }
    },
    
    setTabItemsCount: function(categoryID)
    {
        $H(CategoryTabControl.prototype.tabItemsCounts[categoryID]).each(function(tab) {
            $(tab.key).getElementsByTagName('span')[0].firstChild.nodeValue = ' (' + tab.value + ')';
        });
    },
    
    resetTabItemsCount: function(categoryID)
    {
        CategoryTabControl.prototype.tabItemsCounts[categoryID] = null;
        CategoryTabControl.prototype.updateTabItemsCount(categoryID);
    },

	getActiveTab: function()
	{
		return this.activeTab;
	},

	setTabUrl: function(tabId, url)
	{
		$('tabId').url = url;
	}
}


Backend.Category.PopupSelector = Class.create();
Backend.Category.PopupSelector.prototype = 
{
    element: null,
    
    initialize: function(element)
    {
        this.element = element;

        var self = this;
        var w = window.open('/livecart/backend.category/popup', 'selectCategory', 'width=260, height=450');
        
        // close the popup automatically if closing/reloading page
		Event.observe(window, 'unload', function()
		{
			w.close();
		});
        
        Event.observe(w, 'load', 
            function()
            {
                Event.observe(w.document.getElementById('select'), 'click', function()
                    {
                        var tree = w.Backend.Category.treeBrowser;
                        
                        self.element.down('input').value = tree.getSelectedItemId();
                        
                        var name = '';
                        
                        var parentId = tree.getSelectedItemId();
                        do
                        {       
                            name = tree.getItemText(parentId) + name;;

                            parentId = tree.getParentId(parentId);
                            
                            if (parentId)
                            {
                                name = ' > ' + name;
                            }
                        }
                        while(parentId != 0);

						if (!w.confirm(w.Backend.Category.PopupSelector.prototype.confirmationMsg + "\n\n" + name))
						{
							return false;
						}						
						
						self.element.up('form').down('input.submit').click();

                    	var select = self.element.up('form').down('.select');
						select.value = 'enable_isEnabled';
                    	w.close();

                    });
                    
            	Event.observe(w.document.getElementById('cancel'), 'click', function(e)
                    {
                    	var select = self.element.up('form').down('.select');
						select.value = 'enable_isEnabled';
                    	w.close();
						                    	
                    	Event.stop(e);
                    });
            }
        );
        
    }
}