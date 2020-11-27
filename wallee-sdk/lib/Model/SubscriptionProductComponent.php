<?php
/**
 * wallee SDK
 *
 * This library allows to interact with the wallee payment service.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


namespace Wallee\Sdk\Model;

use \ArrayAccess;
use \Wallee\Sdk\ObjectSerializer;

/**
 * SubscriptionProductComponent model
 *
 * @category    Class
 * @description 
 * @package     Wallee\Sdk
 * @author      customweb GmbH
 * @license     http://www.apache.org/licenses/LICENSE-2.0 Apache License v2
 */
class SubscriptionProductComponent implements ModelInterface, ArrayAccess
{
    const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $swaggerModelName = 'SubscriptionProductComponent';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerTypes = [
        'component_change_weight' => 'int',
        'component_group' => '\Wallee\Sdk\Model\SubscriptionProductComponentGroup',
        'default_component' => 'bool',
        'description' => '\Wallee\Sdk\Model\DatabaseTranslatedString',
        'id' => 'int',
        'linked_space_id' => 'int',
        'maximal_quantity' => 'float',
        'minimal_quantity' => 'float',
        'name' => '\Wallee\Sdk\Model\DatabaseTranslatedString',
        'quantity_step' => 'float',
        'reference' => '\Wallee\Sdk\Model\SubscriptionProductComponentReference',
        'sort_order' => 'int',
        'tax_class' => '\Wallee\Sdk\Model\TaxClass',
        'version' => 'int'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerFormats = [
        'component_change_weight' => 'int32',
        'component_group' => null,
        'default_component' => null,
        'description' => null,
        'id' => 'int64',
        'linked_space_id' => 'int64',
        'maximal_quantity' => null,
        'minimal_quantity' => null,
        'name' => null,
        'quantity_step' => null,
        'reference' => null,
        'sort_order' => 'int32',
        'tax_class' => null,
        'version' => 'int32'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'component_change_weight' => 'componentChangeWeight',
        'component_group' => 'componentGroup',
        'default_component' => 'defaultComponent',
        'description' => 'description',
        'id' => 'id',
        'linked_space_id' => 'linkedSpaceId',
        'maximal_quantity' => 'maximalQuantity',
        'minimal_quantity' => 'minimalQuantity',
        'name' => 'name',
        'quantity_step' => 'quantityStep',
        'reference' => 'reference',
        'sort_order' => 'sortOrder',
        'tax_class' => 'taxClass',
        'version' => 'version'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'component_change_weight' => 'setComponentChangeWeight',
        'component_group' => 'setComponentGroup',
        'default_component' => 'setDefaultComponent',
        'description' => 'setDescription',
        'id' => 'setId',
        'linked_space_id' => 'setLinkedSpaceId',
        'maximal_quantity' => 'setMaximalQuantity',
        'minimal_quantity' => 'setMinimalQuantity',
        'name' => 'setName',
        'quantity_step' => 'setQuantityStep',
        'reference' => 'setReference',
        'sort_order' => 'setSortOrder',
        'tax_class' => 'setTaxClass',
        'version' => 'setVersion'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'component_change_weight' => 'getComponentChangeWeight',
        'component_group' => 'getComponentGroup',
        'default_component' => 'getDefaultComponent',
        'description' => 'getDescription',
        'id' => 'getId',
        'linked_space_id' => 'getLinkedSpaceId',
        'maximal_quantity' => 'getMaximalQuantity',
        'minimal_quantity' => 'getMinimalQuantity',
        'name' => 'getName',
        'quantity_step' => 'getQuantityStep',
        'reference' => 'getReference',
        'sort_order' => 'getSortOrder',
        'tax_class' => 'getTaxClass',
        'version' => 'getVersion'
    ];

    

    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        
        $this->container['component_change_weight'] = isset($data['component_change_weight']) ? $data['component_change_weight'] : null;
        
        $this->container['component_group'] = isset($data['component_group']) ? $data['component_group'] : null;
        
        $this->container['default_component'] = isset($data['default_component']) ? $data['default_component'] : null;
        
        $this->container['description'] = isset($data['description']) ? $data['description'] : null;
        
        $this->container['id'] = isset($data['id']) ? $data['id'] : null;
        
        $this->container['linked_space_id'] = isset($data['linked_space_id']) ? $data['linked_space_id'] : null;
        
        $this->container['maximal_quantity'] = isset($data['maximal_quantity']) ? $data['maximal_quantity'] : null;
        
        $this->container['minimal_quantity'] = isset($data['minimal_quantity']) ? $data['minimal_quantity'] : null;
        
        $this->container['name'] = isset($data['name']) ? $data['name'] : null;
        
        $this->container['quantity_step'] = isset($data['quantity_step']) ? $data['quantity_step'] : null;
        
        $this->container['reference'] = isset($data['reference']) ? $data['reference'] : null;
        
        $this->container['sort_order'] = isset($data['sort_order']) ? $data['sort_order'] : null;
        
        $this->container['tax_class'] = isset($data['tax_class']) ? $data['tax_class'] : null;
        
        $this->container['version'] = isset($data['version']) ? $data['version'] : null;
        
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        return $invalidProperties;
    }

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerTypes()
    {
        return self::$swaggerTypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerFormats()
    {
        return self::$swaggerFormats;
    }


    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$swaggerModelName;
    }

    

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }

    

    /**
     * Gets component_change_weight
     *
     * @return int
     */
    public function getComponentChangeWeight()
    {
        return $this->container['component_change_weight'];
    }

    /**
     * Sets component_change_weight
     *
     * @param int $component_change_weight The change weight determines whether if a component change is considered as upgrade or downgrade. If product component with a weight 10 is changed to a product component with a weight 20, the change is considered as upgrade. On the other hand a change from 20 to 10 is considered as a downgrade.
     *
     * @return $this
     */
    public function setComponentChangeWeight($component_change_weight)
    {
        $this->container['component_change_weight'] = $component_change_weight;

        return $this;
    }
    

    /**
     * Gets component_group
     *
     * @return \Wallee\Sdk\Model\SubscriptionProductComponentGroup
     */
    public function getComponentGroup()
    {
        return $this->container['component_group'];
    }

    /**
     * Sets component_group
     *
     * @param \Wallee\Sdk\Model\SubscriptionProductComponentGroup $component_group 
     *
     * @return $this
     */
    public function setComponentGroup($component_group)
    {
        $this->container['component_group'] = $component_group;

        return $this;
    }
    

    /**
     * Gets default_component
     *
     * @return bool
     */
    public function getDefaultComponent()
    {
        return $this->container['default_component'];
    }

    /**
     * Sets default_component
     *
     * @param bool $default_component When a component is marked as a 'default' component it is used when no other component is selected by the user.
     *
     * @return $this
     */
    public function setDefaultComponent($default_component)
    {
        $this->container['default_component'] = $default_component;

        return $this;
    }
    

    /**
     * Gets description
     *
     * @return \Wallee\Sdk\Model\DatabaseTranslatedString
     */
    public function getDescription()
    {
        return $this->container['description'];
    }

    /**
     * Sets description
     *
     * @param \Wallee\Sdk\Model\DatabaseTranslatedString $description The component description may contain a longer description which gives the subscriber a better understanding of what the component contains.
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->container['description'] = $description;

        return $this;
    }
    

    /**
     * Gets id
     *
     * @return int
     */
    public function getId()
    {
        return $this->container['id'];
    }

    /**
     * Sets id
     *
     * @param int $id The ID is the primary key of the entity. The ID identifies the entity uniquely.
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->container['id'] = $id;

        return $this;
    }
    

    /**
     * Gets linked_space_id
     *
     * @return int
     */
    public function getLinkedSpaceId()
    {
        return $this->container['linked_space_id'];
    }

    /**
     * Sets linked_space_id
     *
     * @param int $linked_space_id The linked space id holds the ID of the space to which the entity belongs to.
     *
     * @return $this
     */
    public function setLinkedSpaceId($linked_space_id)
    {
        $this->container['linked_space_id'] = $linked_space_id;

        return $this;
    }
    

    /**
     * Gets maximal_quantity
     *
     * @return float
     */
    public function getMaximalQuantity()
    {
        return $this->container['maximal_quantity'];
    }

    /**
     * Sets maximal_quantity
     *
     * @param float $maximal_quantity The maximum quantity defines the maximum value which must be entered for the quantity.
     *
     * @return $this
     */
    public function setMaximalQuantity($maximal_quantity)
    {
        $this->container['maximal_quantity'] = $maximal_quantity;

        return $this;
    }
    

    /**
     * Gets minimal_quantity
     *
     * @return float
     */
    public function getMinimalQuantity()
    {
        return $this->container['minimal_quantity'];
    }

    /**
     * Sets minimal_quantity
     *
     * @param float $minimal_quantity The minimal quantity defines the minimum value which must be entered for the quantity.
     *
     * @return $this
     */
    public function setMinimalQuantity($minimal_quantity)
    {
        $this->container['minimal_quantity'] = $minimal_quantity;

        return $this;
    }
    

    /**
     * Gets name
     *
     * @return \Wallee\Sdk\Model\DatabaseTranslatedString
     */
    public function getName()
    {
        return $this->container['name'];
    }

    /**
     * Sets name
     *
     * @param \Wallee\Sdk\Model\DatabaseTranslatedString $name The component name is shown to the subscriber. It should describe in few words what the component does contain.
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->container['name'] = $name;

        return $this;
    }
    

    /**
     * Gets quantity_step
     *
     * @return float
     */
    public function getQuantityStep()
    {
        return $this->container['quantity_step'];
    }

    /**
     * Sets quantity_step
     *
     * @param float $quantity_step The quantity step defines at which interval the quantity can be increased.
     *
     * @return $this
     */
    public function setQuantityStep($quantity_step)
    {
        $this->container['quantity_step'] = $quantity_step;

        return $this;
    }
    

    /**
     * Gets reference
     *
     * @return \Wallee\Sdk\Model\SubscriptionProductComponentReference
     */
    public function getReference()
    {
        return $this->container['reference'];
    }

    /**
     * Sets reference
     *
     * @param \Wallee\Sdk\Model\SubscriptionProductComponentReference $reference The component reference is used to identify the component by external systems and it marks components to represent the same component within different product versions.
     *
     * @return $this
     */
    public function setReference($reference)
    {
        $this->container['reference'] = $reference;

        return $this;
    }
    

    /**
     * Gets sort_order
     *
     * @return int
     */
    public function getSortOrder()
    {
        return $this->container['sort_order'];
    }

    /**
     * Sets sort_order
     *
     * @param int $sort_order The sort order controls in which order the component is listed. The sort order is used to order the components in ascending order.
     *
     * @return $this
     */
    public function setSortOrder($sort_order)
    {
        $this->container['sort_order'] = $sort_order;

        return $this;
    }
    

    /**
     * Gets tax_class
     *
     * @return \Wallee\Sdk\Model\TaxClass
     */
    public function getTaxClass()
    {
        return $this->container['tax_class'];
    }

    /**
     * Sets tax_class
     *
     * @param \Wallee\Sdk\Model\TaxClass $tax_class The tax class of the component determines the taxes which are applicable on all fees linked with the component.
     *
     * @return $this
     */
    public function setTaxClass($tax_class)
    {
        $this->container['tax_class'] = $tax_class;

        return $this;
    }
    

    /**
     * Gets version
     *
     * @return int
     */
    public function getVersion()
    {
        return $this->container['version'];
    }

    /**
     * Sets version
     *
     * @param int $version The version number indicates the version of the entity. The version is incremented whenever the entity is changed.
     *
     * @return $this
     */
    public function setVersion($version)
    {
        $this->container['version'] = $version;

        return $this;
    }
    
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Sets value based on offset.
     *
     * @param integer $offset Offset
     * @param mixed   $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) { // use JSON pretty print
            return json_encode(
                ObjectSerializer::sanitizeForSerialization($this),
                JSON_PRETTY_PRINT
            );
        }

        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}


