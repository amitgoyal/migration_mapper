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
        'export_content' => $this->t('Just Export Site Content'),
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

    $form['json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paste JSON'),
      '#description' => $this->t('Please paste valid json you wish to generate migration form.'),
      '#states' => [
        'visible' => [
          ':input[name="import_type"]' => ['value' => 'json'],
        ],
      ],
      '#attributes' => [
        'class' => [$class],
      ],
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
        foreach ($map as $row) {
          $form[$row] = [
            '#type' => 'select',
            '#title' => $this->t('Row @row', ['@row' => $row]),
            '#options' => $field_options,
          ];
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
      if (!empty($data['module_path'])) {
        $form['path'] = [
          '#markup' => $this->t('<b>Expected Path: </b> @path <br/>', ['@path' => $data['module_path']]),
        ];
      }
      if (!empty($data['phrased_yml'])) {
        /*$parser = new Parser();
        $phrased = $parser->parse($data['phrased_yml'], TRUE);
         */
        $yaml = Yaml::dump($data['phrased_yml']);
        $form['config'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Migration config:'),
          '#default_value' => $yaml,
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
            if (empty($submitted_json)) {
              $form_state->setErrorByName('json', 'Please Enter Some Json');
            }
            else {
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
                if (!empty($decode)) {
                  foreach ($decode as $row => $val) {
                    $opt[$row] = $row;
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
    $data = [];
    $data['langcode'] = 'en';
    $data['status'] = TRUE;
    $data['id'] = 'import_' . $bundal;
    $data['label'] = 'IMPORT-' . $bundal;
    $data['migration_tags'] = ['Embedded'];
    $data['migration_group'] = 'Data';

    // If JSON:
    if (!empty($form_values['jsonData'])) {
      $json = $form_values['jsonData'];
      unset($form_values['jsonData']);
      $default_plugin_type = 'embedded_data';
      $data['source']['plugin'] = $default_plugin_type;
    }

    if (!empty($file_path)) {
      $default_plugin_type = 'plugin';
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

    // @TODO if csv use CSV pluging.

    $data['source']['data_rows'] = [];

    $flipped_values = array_flip($form_values);
    unset($flipped_values['NA']);
    foreach ($flipped_values as $field_key => $mapped_key) {
      $field_info = $field_defs[$field_key];
      if (is_object($field_info)) {
        $field_type = $field_info->getType();
      }

    }
    $data['destination'] = [
      'plugin' => 'entity:' . $type,
    ];

    return $data;
  }

}
