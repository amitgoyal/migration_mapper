<?php

namespace Drupal\migration_mapper\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManager;
use League\Csv\Reader;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * Class MigrationAdminForm.
 */
class MigrationAdminForm extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\File\FileSystem definition.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  protected $item_selector_items = [];

  /**
   * Constructs a new MigrationAdminForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileSystem $file_system,
    EntityFieldManager $entity_field_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migration_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type_definations = $this->entityTypeManager->getDefinitions();
    $options = [];
    $allow_config_entities = FALSE;
    foreach ($entity_type_definations as $name => $definition) {
      // @TODO workout configEntities
      if ($definition instanceof ContentEntityType) {
        $type = $definition->get('bundle_entity_type');
        if (!empty($type)) {
          $types = $this->entityTypeManager->getStorage($type)->loadMultiple();
          if (count($types) != 0) {
            foreach ($types as $key => $nodeType) {
              $new_name = $name . '---' . $key;
              $options[$new_name] = $new_name;
            }
          }
        }
        else {
          if ($definition->id() == 'user') {
            $new_name = 'user---user';
            $options[$new_name] = $new_name;
          }
        }
      }
      if ($allow_config_entities == TRUE) {
        $options[$name] = $name;
      }
    }
    $class = 'step1';
    if (!empty($form_state->getValue('csvheader'))) {
      // $class = 'hidden';.
    }
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#description' => $this->t('Please Select the Entity you wish to migrate to.'),
      '#options' => $options,
      '#size' => 1,
      '#required' => TRUE,
      '#attributes' => [
        'class' => [$class],
      ],
    ];
    $form['import_type'] = [
      '#type' => 'radios',
      '#options' => [
        'csv' => $this->t('CSV'),
        'json' => $this->t('JSON'),
        'xml' => $this->t('XML'),
        // 'export_content' => $this->t('Just Export Site Content'),.
      ],
      '#title' => $this->t('Import type'),
      '#description' => $this->t('Please select the import type you wish to use.'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => [$class],
      ],
    ];
    // @TODO find out why states dont like managed_file fields.
    if ($class != 'hidden') {
      $form['file_field_set'] = [
        '#type' => 'fieldset',
        '#title' => t('CSV File'),
        '#states' => [
          'visible' => [
            ':input[name="import_type"]' => ['value' => 'csv'],
          ],
        ],
        '#attributes' => [
          'class' => [$class],
        ],
      ];
    }
    if ($class != 'hidden') {
      $form['file_field_json'] = [
        '#type' => 'fieldset',
        '#title' => t('JSON File'),
        '#states' => [
          'visible' => [
            ':input[name="import_type"]' => ['value' => 'json'],
          ],
        ],
        '#attributes' => [
          'class' => [$class],
        ],
      ];
    }
    if ($class != 'hidden') {
      $form['file_field_xml'] = [
        '#type' => 'fieldset',
        '#title' => t('XML File'),
        '#states' => [
          'visible' => [
            ':input[name="import_type"]' => ['value' => 'xml'],
          ],
        ],
        '#attributes' => [
          'class' => [$class],
        ],
      ];
    }
    // See https://www.drupal.org/node/2705471.
    $form['file_field_set']['m_csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV File'),
      '#upload_location' => 'public://migrate_csv/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#description' => $this->t('Please select A CSV file to create migration from.'),
    ];

    $form['file_field_json']['m_json_file'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://migrate_json/',
      '#upload_validators' => [
        'file_validate_extensions' => ['json'],
      ],
      '#description' => $this->t('Please select A JSON file to create migration from.'),
    ];

    $form['file_field_json']['json_markup'] = [
      '#type' => 'inline_template',
      '#template' => '{{ somecontent }}',
      '#context' => [
        'somecontent' => "OR"
      ]
    ];

    $form['file_field_json']['json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paste JSON'),
      '#description' => $this->t('Please paste valid json you wish to generate migration form.'),
      '#attributes' => [
        'class' => [$class],
      ],
    ];

    $form['file_field_xml']['m_xml_file'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://migrate_xml/',
      '#upload_validators' => [
        'file_validate_extensions' => ['xml'],
      ],
      '#description' => $this->t('Please select A XML file to create migration from.'),
    ];

    if (!empty($form_state->getValue('csvheader'))) {
      $form = [];
      $form['module_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Module Name'),
        '#description' => 'As With any Migration you must create a custom module.',
        '#default_value' => 'mymodule',
      ];
      $map = $form_state->getValue('csvheader');
      if (!empty($map['filePath'])) {
        $form['filePath'] = [
          '#type' => 'hidden',
          '#value' => $map['filePath'],
        ];
        unset($map['filePath']);
      }
      if (!empty($map['jsonData'])) {
        $form['jsonData'] = [
          '#type' => 'hidden',
          '#value' => $map['jsonData'],
        ];
        unset($map['jsonData']);
      }
      elseif (!empty($map['filePathJson'])) {
        $form['filePathJson'] = [
          '#type' => 'hidden',
          '#value' => $map['filePathJson'],
        ];
        unset($map['filePathJson']);
      }
      elseif (!empty($map['filePathXML'])) {
        $form['filePathXML'] = [
          '#type' => 'hidden',
          '#value' => $map['filePathXML'],
        ];
        unset($map['filePathXML']);
      }
      if (!empty($map['entity_type'])) {
        $form['entity_type'] = [
          '#type' => 'hidden',
          '#value' => $map['entity_type'],
        ];
        unset($map['entity_type']);
      }

      if (!empty($map['field_options'])) {
        $field_options = $map['field_options'];
        unset($map['field_options']);
        foreach ($map as $row => $row_val) {
          if (is_array($row)) {
            foreach ($row as $row_key => $row_val) {
              $form[$row_key] = [
                '#type' => 'select',
                '#title' => $this->t('Row @row', ['@row' => $row_key]),
                '#options' => $field_options,
              ];
            }
          }
          else {
            $form[$row] = [
              '#type' => 'select',
              '#title' => $this->t('Row @row', ['@row' => $row]),
              '#options' => $field_options,
            ];
          }
        }
      }
      $form['has_mapped_data'] = [
        '#type' => 'hidden',
        '#value' => 'yes',
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Export'),
      ];
    }
    else {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ];
    }
    if (!empty($form_state->getValue('final_export'))) {
      $form = [];
      $data = $form_state->getValue('final_export');
      if (!empty($data['module_path']) && !empty($data['drush_import'])) {
        $form['path'] = [
          '#markup' => $this->t('<b>Expected Path: </b> @path <br/> <b>Drush:</b> After you created and enabled your module Run "drush ms" and make sure it shows up then run "drush mi @command" <br/> to rollback run "drush mr @command" See Notes below.', [
            '@path' => $data['module_path'],
            '@command' => $data['drush_import'],
          ]),
        ];
      }
      if (!empty($data['phrased_yml'])) {
        $yaml = Yaml::dump($data['phrased_yml'], '6', 2);
        $yaml = str_replace('~~~', '', $yaml);
        $form['config'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Migration config:'),
          '#default_value' => $yaml,
          '#rows' => 50,
        ];

        $form['note'] = [
          '#type' => 'inline_template',
          '#theme' => 'config_notes',
          '#drush_import' => $data['drush_import'],
          '#module_name' => $data['module_name'],
          '#weight' => 200,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('has_mapped_data'))) {
      $form_values = $form_state->getValues();
      $values_to_strip = [
        'submit',
        'form_build_id',
        'form_token',
        'form_id',
        'op',
      ];
      $entity_type = $form_state->getValue('entity_type');
      if (empty($form_state->getValue('module_name'))) {
        $form_state->setErrorByName('module_name', 'You must provide a custom module name.');
      }
      else {
        // Build the module name.
        $path = '';
        $type_explode = explode('---', $entity_type);
        if (!empty($type_explode[0]) && !empty($type_explode[1])) {
          $type = $type_explode[0];
          $bundal = $type_explode[1];
          $modulename = $form_state->getValue('module_name');
          $path = $modulename . '/config/install/migrate_plus.migration.' . $bundal . '.yml';
        }
      }

      foreach ($form_values as $key => $value) {
        if (!empty($key) && in_array($key, $values_to_strip)) {
          unset($form_values[$key]);
        }
      }

      // @TODO handel $form_values and build migration.
      $output = [];
      if (!empty($path)) {
        $output['module_path'] = $path;
      }
      $output['module_name'] = $modulename;
      $output['drush_import'] = 'import_' . $bundal;
      $output['phrased_yml'] = $this->buildMigrationExport($form_values);
      $form_state->setValue('final_export', $output);
      $form_state->setRebuild(TRUE);

    }
    else {
      parent::validateForm($form, $form_state);
      $field_defs = [];
      $entity_or_array = $this->getSelectedEntityFieldDeff($form_state->getValue('entity_type'));
      $submitted_type = $form_state->getValue('entity_type');
      if (is_array($entity_or_array)) {
        // We Have field defs.
        foreach ($entity_or_array as $key => $val) {
          $field_defs[$key] = $key;
        }
      }
      if (is_object($entity_or_array)) {
        drupal_set_message($this->t('TODO: WORK OUT HOW TO DEAL WITH non bundal entities'), 'warning');
        // dump($entity_or_array->);
        /*
        // @TODO wotk out how to get all properties of a config entity
        drupal_set_message($this->t('TODO:
        WORK OUT HOW TO DEAL WITH config entities'), 'warning');
        dump($entity_or_array->getEntityType()->id());
        $id = $entity_or_array->getEntityType()->id();
        $query = $entity_or_array->getQuery()->sort($id)->execute();
         */
      }
      $import_type = $form_state->getValue('import_type');
      if (!empty($import_type)) {
        switch ($import_type) {
          case 'csv':
            $m_csv_file = $form_state->getValue('m_csv_file');
            if (empty($m_csv_file)) {
              $form_state->setErrorByName('m_csv_file', 'Please Upload a CSV');
            }
            else {
              // Try to validate the file.
              if (is_array($m_csv_file)) {
                $fid = reset($m_csv_file);
                // load_file =.
                $file = $this->entityTypeManager->getStorage('file')->load($fid);
                if (is_object($file)) {
                  $uri = $file->getFileUri();
                  $url = file_create_url($uri);
                  $path = $this->fileSystem->realpath($uri);
                  $input_csv = Reader::createFromPath($path);
                  $input_csv->setDelimiter(',');
                  try {
                    $fetchAssoc = $input_csv->fetchAssoc(0);
                  }
                  catch (\Exception $e) {
                    $form_state->setErrorByName('m_csv_file', 'invalid csv format');
                  }
                }
              }
              $opt = [
                'field_options' => $field_defs,
                'filePath' => $path,
                'entity_type' => $submitted_type,
              ];
              if (!empty($fetchAssoc)) {
                foreach ($fetchAssoc as $row => $val) {
                  foreach ($val as $key => $csv_data) {
                    $opt[$key] = $key;
                  }
                  break;
                }
              }
              $form_state->setValue('csvheader', $opt);
              $form_state->setRebuild(TRUE);
            }

            break;

          case 'json':
            $submitted_json = $form_state->getValue('json');
            $m_json_file = $form_state->getValue('m_json_file');
            if (empty($submitted_json) && empty($m_json_file)) {
              $form_state->setErrorByName('json', 'Please Enter Some Json');
            }
            else {
              if (!empty($m_json_file)) {
                // Read JSON File
                $fid = reset($m_json_file);
                // load_file =.
                $file = $this->entityTypeManager->getStorage('file')->load($fid);
                if (is_object($file)) {
                  $uri = $file->getFileUri();
                  $url = file_create_url($uri);
                  $path = $this->fileSystem->realpath($uri);
                  $submitted_json = file_get_contents($path);
                }
              }

              // Strip any unwanted data.
              $submitted_json = str_replace('\r\n', '', $submitted_json);
              // Try to phrase.
              $decode = json_decode($submitted_json, TRUE);
              if (!is_array($decode) || count($decode) == 0) {
                $form_state->setErrorByName('json', 'Please Enter Some Valid Json');
              }
              else {
                $opt = [
                  'field_options' => $field_defs,
                  'jsonData' => $submitted_json,
                  'entity_type' => $submitted_type,
                ];

                if (!empty($m_json_file)) {
                  $opt['filePathJson'] = $url;
                  unset($opt['jsonData']);
                }
                if (!empty($decode)) {
                  foreach ($decode as $row => $val) {
                    if (is_array($val)) {
                      foreach ($val as $k => $value) {
                        $opt[$k] = $value;
                      }
                    }
                    else {
                      $opt[$row] = $row;
                    }
                  }
                }
                $form_state->setValue('csvheader', $opt);
                $form_state->setRebuild(TRUE);
              }
            }
            break;

          case 'xml':
            $m_xml_file = $form_state->getValue('m_xml_file');
            if (empty($m_xml_file)) {
              $form_state->setErrorByName('m_xml_file', 'Please Upload XML File.');
            }
            else {
              if (!empty($m_xml_file)) {
                // Read XML File
                $fid = reset($m_xml_file);
                // load_file =.
                $file = $this->entityTypeManager->getStorage('file')->load($fid);
                if (is_object($file)) {
                  $uri = $file->getFileUri();
                  $url = file_create_url($uri);
                  $path = $this->fileSystem->realpath($uri);
                  $submitted_xml = file_get_contents($path);

                  // Getting root tag of XML
                  $xml = simplexml_load_string($submitted_xml);
                  $item_root_tag = $xml->getName();

                  // Converting xml data into array format.
                  $xml_encoder = new XmlEncoder();
                  $decode = $xml_encoder->decode($submitted_xml, 'xml');

                  // Building item_selector_items array with all the XML tags.
                  $this->_get_data_level($decode);
                  array_unshift($this->item_selector_items, $item_root_tag);

                  $xml_tags = $this->item_selector_items;
                  $arr_level_count = count($xml_tags);

                  // Getting fields for mapping.
                  for ($i = 0; $i < $arr_level_count; $i++) {
                    $keys = array_keys($decode);
                    $decode = $decode[$keys[0]];
                  }
                }
              }
              // Try to phrase.
              if (isset($decode) && count($decode) == 0) {
                $form_state->setErrorByName('m_xml_file', 'Please Upload some valid XML');
              }
              else {
                $opt = [
                  'field_options' => $field_defs,
                  'filePathXML' => $url,
                  'entity_type' => $submitted_type,
                ];
                if (!empty($decode)) {
                  foreach ($decode as $row => $val) {
                    if (is_array($val)) {
                      foreach ($val as $k => $value) {
                        $opt[$k] = $value;
                      }
                    }
                    else {
                      $opt[$row] = $row;
                    }
                  }
                }
                $form_state->setValue('csvheader', $opt);
                $form_state->setRebuild(TRUE);
              }
            }
            break;

          case 'export_content':
            drupal_set_message('export_content');
            break;
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This should never be called.
    drupal_set_message('hello');
  }

  /**
   * Helper function to get the Field definitions.
   *
   * @param string $type_submitted
   *   The submited entity type.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface|\Drupal\Core\Field\FieldDefinitionInterface[]
   *   Public function getSelectedEntityFieldDeff.
   */
  public function getSelectedEntityFieldDeff($type_submitted) {
    $type_explode = explode('---', $type_submitted);
    if (!empty($type_explode[0]) && !empty($type_explode[1])) {
      $type = $type_explode[0];
      $bundal = $type_explode[1];
    }
    else {
      $type = $type_submitted;
    }
    if (!empty($bundal)) {
      $feild_defs = [];
      $field_defs['NA'] = 'NA';
      return array_merge($field_defs, $this->entityFieldManager->getFieldDefinitions($type, $bundal));

    }
    else {
      return $this->entityTypeManager->getStorage($type);
    }
  }

  /**
   * This function builds the migration data.
   *
   * @param array $form_values
   *   Public function buildMigrationExport array form_values.
   *
   * @return array
   *   This returns an array to be phrased.
   */
  public function buildMigrationExport(array $form_values) {
    unset($form_values['has_mapped_data']);
    $module_name = $form_values['module_name'];
    $entity_type = $form_values['entity_type'];
    $type_explode = explode('---', $entity_type);
    $bundal = '';
    $type = '';
    if (!empty($type_explode[0]) && !empty($type_explode[1])) {
      $type = $type_explode[0];
      $bundal = $type_explode[1];
    }

    $field_defs = $this->getSelectedEntityFieldDeff($entity_type);
    unset($form_values['module_name']);
    unset($form_values['entity_type']);

    if (!empty($form_values['filePath'])) {
      $file_path = $form_values['filePath'];
      unset($form_values['filePath']);
    }
    if (!empty($form_values['filePathJson'])) {
      $file_path_json = $form_values['filePathJson'];
      unset($form_values['filePathJson']);
    }
    if (!empty($form_values['filePathXML'])) {
      $file_path_xml = $form_values['filePathXML'];
      unset($form_values['filePathXML']);
    }
    $data = [];
    $data['langcode'] = 'en';
    $data['status'] = TRUE;
    $data['id'] = 'import_' . $bundal;
    $data['label'] = 'IMPORT-' . $bundal;
    $data['migration_tags'] = ['Embedded'];
    $data['migration_group'] = 'default';
    $data['source'] = [];
    // If JSON:
    if (!empty($form_values['jsonData'])) {
      $json = $form_values['jsonData'];
      unset($form_values['jsonData']);
      $default_plugin_type = 'embedded_data';
    }
    elseif (!empty($file_path_json)) {
      $default_plugin_type = 'url';
      $json_values = [
        'urls' => $file_path_json,
        'data_fetcher_plugin' => 'http',
        'data_parser_plugin' => 'json',
      ];
    }
    elseif (!empty($file_path_xml)) {
      $default_plugin_type = 'url';
      $xml_values = [
        'urls' => $file_path_xml,
        'data_fetcher_plugin' => 'http',
        'data_parser_plugin' => 'xml',
      ];
    }
    if (!empty($file_path)) {
      $default_plugin_type = 'csv';
      $csv_values = [
        'path' => $file_path,
        'header_row_count' => 1,
      ];
    }
    $data['source'] = [
      'plugin' => $default_plugin_type,
    ];
    if (!empty($csv_values)) {
      $data['migration_tags'] = ['CSV'];
      $data['source'] = array_merge($data['source'], $csv_values);
    }
    if (!empty($json_values)) {
      $data['migration_tags'] = ['JSON'];
      $data['source'] = array_merge($data['source'], $json_values);
    }
    if (!empty($xml_values)) {
      $data['migration_tags'] = ['XML'];
      $data['source'] = array_merge($data['source'], $xml_values);
    }

    // @TODO if csv use CSV pluging.
    $flipped_values = array_flip($form_values);
    $mapped_values = [];
    unset($flipped_values['NA']);
    unset($flipped_values['']);
    foreach ($flipped_values as $field_key => $mapped_key) {
      if (!empty($field_key)) {
        if (!empty($field_defs[$field_key])) {
          $field_info = $field_defs[$field_key];
          if (is_object($field_info)) {
            $mapped_values[$mapped_key] = $field_info;
          }
        }
        else {
          drupal_set_message(t('@key With @Value could not be mapped'), ['@key' => $field_key, '@Value' => $mapped_key]);
        }
      }
    }
    // We now have Field info with data key ad array key.
    if (!empty($mapped_values)) {
      if (!empty($json)) {
        $data['source']['ids'] = [
          'id' => [
            'type' => 'integer',
          ],
        ];
        $embed_json_data = $this->setJsonDataRow($mapped_values, $json);
        foreach ($embed_json_data as $item) {
          $data['source']['data_rows'][] = $item;
        }
      }
      elseif (!empty($file_path_json)) {
        $data['source']['ids'] = [];
        foreach ($mapped_values as $def_key => $def_val) {
          $data['source']['ids'][$def_key]['type'] = "string";
          break;
        }
        $data['source']['fields'] = $this->setJsonFields($mapped_values, $file_path_json);
      }
      elseif (!empty($file_path_xml)) {
        $data['source']['ids'] = [];
        foreach ($mapped_values as $def_key => $def_val) {
          $data['source']['ids'][$def_key]['type'] = "string";
          break;
        }
        $data['source']['item_selector'] = "/" . implode("/", $this->item_selector_items);
        $data['source']['fields'] = $this->setXMLFields($mapped_values, $file_path_xml);
      }
      // TODO: IF CSV MAKE Keys HERE.
      if (!empty($file_path)) {
        $data['source']['keys'] = [];
        // Need to make first colum the key.
        foreach ($mapped_values as $def_key => $def_val) {
          $data['source']['keys'][] = $def_key;
          break;
        }
        $data['source']['column_names'] = $this->setCsvKeys($mapped_values, $file_path);
      }
    }

    $data['process'] = [];
    $data['process']['type'] = [
      'plugin' => 'default_value',
      'default_value' => $bundal,
    ];
    $data['process']['langcode'] = [
      'plugin' => 'default_value',
      'source' => 'language',
      'default_value' => 'und',
    ];
    // @TODO remove this -> Setting USER ID.
    $data['process']['uid'] = [
      'plugin' => 'default_value',
      'default_value' => 1,
    ];
    if (!empty($json)) {
      $prosess_field_types = $this->prosessFieldTypes($mapped_values, TRUE);
    }
    else {
      $prosess_field_types = $this->prosessFieldTypes($mapped_values);
    }

    foreach ($prosess_field_types as $pkey => $prosessed_type) {
      $field_name = $prosessed_type['name'];
      $field_type = $prosessed_type['type'];
      $field_config = $prosessed_type['config'];
      if (!empty($json)) {
        $advanced_config_required = $this->formatExportPluginOnConfig($field_name, $pkey, $field_config, TRUE);
      }
      else {
        $advanced_config_required = $this->formatExportPluginOnConfig($field_name, $pkey, $field_config);
      }
      if (is_array($advanced_config_required) && count($advanced_config_required) != 0) {
        $data['process'][$pkey] = $advanced_config_required;
        //$data['process'][$pkey] = [
        //  'plugin' =>  'entity_generate',
        //  'source' => $field_name,
        //  'entity_type' => 'taxonomy_term',
        //  'bundle_key' => 'vid',
        //  'bundle' => 'dpe_exhibition_document_status',
        //  'value_key' => 'name',
        //];
      }
      else {
        $data['process'][$pkey] = $field_name;
      }
    }

    if ($bundal == 'user') {
      // check That the name key exists
      if (!array_key_exists('name', $mapped_values)) {
        $data['process']['name'] = 'WARNING THIS MUST BE SET';
        drupal_set_message(t('Your User Import Needs a "name"'), 'warning');
      }
    }

    $data['destination'] = [
      'plugin' => 'entity:' . $type,
    ];

    // SOOOO important.
    $data['dependencies'] = [
      'enforced' => [
        'module' => [$module_name],
      ],
    ];

    return $data;
  }

  /**
   * Function to return embeded data rows.
   *
   * @param array $mapped_keys
   *   An Array of mapped keys with field type object.
   * @param string $json
   *   The raw submitted json data for embedding.
   *
   * @return array
   *   This returns an array.
   */
  private function setJsonDataRow(array $mapped_keys, $json) {
    $rows = [];
    $id = 1;
    $json = json_decode($json, TRUE);
    foreach ($json as $item_key => $item) {
      if (is_array($item)) {
        $rows[$id]['id'] = $id;
        foreach ($item as $key => $value) {
          if (!empty($mapped_keys[$key]) && is_object($mapped_keys[$key])) {
            $field_config = $mapped_keys[$key];
            $name = $field_config->getName();
            $rows[$id][$name] = $value;
          }
        }
        $id++;
      }
    }
    return $rows;
  }

  /**
   * Function to return embeded data keys.
   *
   * @param array $mapped_keys
   *   An Array of mapped keys with field type object.
   * @param string $file_path
   *   The file path of csv.
   *
   * @return array
   *   This returns an array.
   */
  private function setCsvKeys(array $mapped_keys, $file_path) {
    $rows = [];
    $id = 0;
    foreach ($mapped_keys as $key => $config) {
      if (is_object($config)) {
        // Dang you yaml dump.
        $string_id = '~~~' . $id . '~~~';
        $rows[$string_id] = [
          $key => $key,
        ];
        $id++;
      }
    }
    return $rows;
  }

  /**
   * Function to return embeded data keys.
   *
   * @param array $mapped_keys
   *   An Array of mapped keys with field type object.
   * @param string $file_path
   *   The file path of json.
   *
   * @return array
   *   This returns an array.
   */
  private function setJsonFields(array $mapped_keys, $file_path) {
    $rows = [];
    foreach ($mapped_keys as $key => $config) {
      if (is_object($config)) {
        // Dang you yaml dump.
        $rows[] = [
          'name' => $key,
          'label' => $key,
          'selector' => $key,
        ];
      }
    }
    return $rows;
  }

  /**
   * Function to return embeded data keys.
   *
   * @param array $mapped_keys
   *   An Array of mapped keys with field type object.
   * @param string $file_path
   *   The file path of xml.
   *
   * @return array
   *   This returns an array.
   */
  private function setXMLFields($mapped_keys, $file_path_xml) {
    $rows = [];
    foreach ($mapped_keys as $key => $config) {
      if (is_object($config)) {
        // Dang you yaml dump.
        $rows[] = [
          'name' => $key,
          'label' => $key,
          'selector' => $key,
        ];
      }
    }
    return $rows;
  }

  /**
   * Helper function to get all the array indexes within which actual data exists.
   * This has been written to find out xpath from xml/json files/arrays.
   *
   * @param $array
   *    This is the array with all the data parsed from xml/json.
   */
  private function _get_data_level($array) {
    if (count($array) == 1) {
      $this->item_selector_items[] = key($array);
      $this->_get_data_level($array[key($array)]);
    }
  }

  /**
   * Helper function to format "process" key in config export.
   *
   * @param array $mapped_keys
   *   An Array of mapped keys with field type object.
   * @param bool $json
   *   This is to flag json field sourse map to same filed.
   *
   * @return array
   *   This will return an array.
   */
  private function prosessFieldTypes(array $mapped_keys, $json = FALSE) {
    $rows = [];
    foreach ($mapped_keys as $key => $config) {
      if (is_object($config)) {
        $name = $config->getName();
        if ($json == TRUE) {
          $rows[$name] = [
            'name' => $name,
            'type' => $config->getType(),
            'config' => $config,
          ];
        }
        else {
          // Is a CSV so map destination with keys.
          $rows[$name] = [
            'name' => $key,
            'type' => $config->getType(),
            'config' => $config,
          ];
        }

      }
    }
    return $rows;
  }

  /**
   * A function to format advanced field process plugins
   *
   * @param string $field_name
   *   The real field name.
   * @param string $pkey
   *   The mapped field
   * @param mixed $field_config
   *   This can be bacefield def or FieldConfig obj.
   * @param bool $json
   *   This is to flag json field sourse map to same filed.
   *
   * @return array
   *   This returns an array.
   */
  private function formatExportPluginOnConfig($field_name, $pkey, $field_config, $json = FALSE) {
    if ($json == TRUE) {
      $field_name = $pkey;
    }
    $return = [];
    $type = $field_config->getType();
    // @TODO the main Development IS here.
    $special_types = [
      'entity_reference',
      'text_with_summary',
      'datetime',
    ];
    if (in_array($type, $special_types)) {
      // @todo query config to find out what values for processors.
      switch ($type) {
        case 'datetime':
          // THIS IS SCARRY !!! as every one has a different format.
          $name = $field_config->getName();
          $return = [
            'plugin' => 'format_date',
            'from_timezone' => 'TODO',
            'source' => $field_name,
            'to_timezone' => 'UTC',
          ];
          break;

        case 'entity_reference':
          $entity_type_id = $field_config->getTargetEntityTypeId();
          $handler_settings = $field_config->getSettings('handler_settings');
          $storage_config = $field_config->getFieldStorageDefinition();

          $settings = $storage_config->getSettings();
          if (!empty($settings['target_type'])) {
            $return = [
              'plugin' => 'entity_generate',
              'source' => $field_name,
              'entity_type' => $settings['target_type'],
              'ignore_case' => TRUE,
            ];
            if (!empty($handler_settings['handler_settings']['target_bundles'])) {
              $target_bundals = implode(',', $handler_settings['handler_settings']['target_bundles']);
              $return['bundle'] = $target_bundals;
            }
            // Could be a view reffrince.
            if (!empty($handler_settings['handler_settings']['view'])) {
              $view_name = $handler_settings['handler_settings']['view']['view_name'];
              $view = $this->entityTypeManager->getStorage('view')->load($view_name);
              $dependencies = $view->getExecutable()->getDependencies();
              $bundle_keys = [];
              if (!empty($dependencies['config'])) {
                $config_types = [];
                foreach ($dependencies['config'] as $config_item) {
                  $check = explode('.type.', $config_item);
                  if (!empty($check[1])) {
                    $bundle_keys[] = $check[1];
                  }
                }
              }
              if (count($bundle_keys) != 0) {
                $target_bundals = implode(',', $bundle_keys);
                $return['bundle'] = $target_bundals;
              }
            }

            // @TODO find out how to get the bundal.
            if ($settings['target_type'] == 'node') {
              $return['bundle_key'] = 'nid';
              $return['value_key'] = 'title';
            }
            if ($settings['target_type'] == 'taxonomy_term') {
              $return['bundle_key'] = 'vid';
              $return['value_key'] = 'name';
            }
            if (empty($return['bundle_key'])) {
              $return['bundle_key'] = 'TODO';
              $return['bundle'] = 'TODO';
              $return['value_key'] = 'TODO';
            }
            if (empty($return['bundle'])) {
              $return['bundle'] = 'TODO';
            }
          }
          else {
            // Cant find a entity type.
            $return = [
              'plugin' => 'entity_generate',
              'source' => $field_name,
            ];
          }
          break;

        case 'text_with_summary':
          $name = $field_config->getName();
          $return = [
            $name . '/value' => $field_name,
            $name . '/format' => [
              'plugin' => 'default_value',
              'default_value' => 'full_html',
            ]
          ];
          break;
      }
    }
    return $return;
  }

}
