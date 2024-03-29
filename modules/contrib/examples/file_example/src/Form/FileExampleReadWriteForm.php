<?php

namespace Drupal\file_example\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\stream_wrapper_example\SessionHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * File test form class.
 *
 * @ingroup file_example
 */
class FileExampleReadWriteForm extends FormBase {

  /**
   * Interface of the "state" service for site-specific data.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Object used to get request data, such as the session.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Service for manipulating a file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Service for fetching a stream wrapper for a file or directory.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The session helper.
   *
   * @var \Drupal\stream_wrapper_example\SessionHelper
   */
  protected $sessionHelper;

  /**
   * Service for invoking hooks and other module operations.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new FileExampleReadWriteForm page.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   Storage interface for state data.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   Interface for common file system operations.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   Interface to obtain stream wrappers used to manipulate a given file
   *   scheme.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Interface to get information about the status of modules and other
   *   extensions.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Access to the current request, including to session objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\stream_wrapper_example\SessionHelper $session_helper
   *   Session helper.
   */
  public function __construct(
    StateInterface $state,
    FileSystemInterface $file_system,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    ModuleHandlerInterface $module_handler,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    SessionHelper $session_helper
  ) {
    $this->state = $state;
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->moduleHandler = $module_handler;
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->sessionHelper = $session_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static(
      $container->get('state'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager'),
      $container->get('module_handler'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('stream_wrapper_example.session_helper')
    );
    $form->setMessenger($container->get('messenger'));
    $form->setStringTranslation($container->get('string_translation'));
    return $form;
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'file_example_readwrite';
  }

  /**
   * Get the default file.
   *
   * This appears in the first block of the form.
   *
   * @return string
   *   The URI of the default file.
   */
  protected function getDefaultFile() {
    $default_file = $this->state->get('file_example_default_file', 'session://drupal.txt');
    return $default_file;
  }

  /**
   * Set the default file.
   *
   * Set a default URI of the file used for read and write operations.
   *
   * @param string $uri
   *   URI to save for future display in the form.
   */
  protected function setDefaultFile($uri) {
    $this->state->set('file_example_default_file', (string) $uri);
  }

  /**
   * Get the default directory.
   *
   * @return string
   *   The URI of the default directory.
   */
  protected function getDefaultDirectory() {
    $default_directory = $this->state->get('file_example_default_directory', 'session://directory1');
    return $default_directory;
  }

  /**
   * Set the default directory.
   *
   * @param string $uri
   *   URI to save for later form display.
   */
  protected function setDefaultDirectory($uri) {
    $this->state->set('file_example_default_directory', (string) $uri);
  }

  /**
   * Utility function to check for and return a managed file.
   *
   * In this demonstration code we don't necessarily know if a file is managed
   * or not, so often need to check to do the correct behavior. Normal code
   * would not have to do this, as it would be working with either managed or
   * unmanaged files.
   *
   * @param string $uri
   *   The URI of the file, like public://test.txt.
   *
   * @return \Drupal\file\Entity\FileInterface|bool
   *   A file object that matches the URI, or FALSE if not a managed file.
   */
  protected function getManagedFile($uri) {
    // We'll use an entity query to get the managed part of the file.
    $storage = $this->entityTypeManager->getStorage('file');
    $query = $storage->getQuery()
      ->condition('uri', $uri);
    $fid = $query->execute();
    if (!empty($fid)) {
      // Now that we have a fid, we can load it.
      $file_object = $storage->load(reset($fid));
      return $file_object;
    }
    // Return FALSE because there's no managed file for that URI.
    return FALSE;
  }

  /**
   * Prepare Url objects to prevent exceptions by the URL generator.
   *
   * Helper function to get us an external URL if this is legal, and to catch
   * the exception Drupal throws if this is not possible.
   *
   * In Drupal 8, the URL generator is very sensitive to how you set things
   * up, and some functions, in particular LinkGeneratorTrait::l(), will throw
   * exceptions if you deviate from what's expected. This function will raise
   * the chances your URL will be valid, and not do this.
   *
   * @param \Drupal\file\Entity\File|string $file_object
   *   A file entity object.
   *
   * @return \Drupal\Core\Url
   *   A Url object that can be displayed as an internal URL.
   */
  protected function getExternalUrl($file_object) {
    if ($file_object instanceof FileInterface) {
      $uri = $file_object->getFileUri();
    }
    else {
      // A little tricky, since file.inc is a little inconsistent, but often
      // this is a Uri.
      $uri = file_create_url($file_object);
    }

    try {
      // If we have been given a PHP stream URI, ask the stream itself if it
      // knows how to create an external URL.
      $wrapper = $this->streamWrapperManager->getViaUri($uri);
      if ($wrapper) {
        $external_url = $wrapper->getExternalUrl();
        // Some streams may not have the concept of an external URL, so we
        // check here to make sure, since the example assumes this.
        if ($external_url) {
          $url = Url::fromUri($external_url);
          return $url;
        }
      }
      else {
        $url = Url::fromUri($uri);
        // If we did not throw on ::fromUri (you can), we return the URL.
        return $url;
      }
    }
    catch (\Exception $e) {
      return FALSE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_file = $this->getDefaultFile();
    $default_directory = $this->getDefaultDirectory();

    $form['description'] = [
      '#markup' => $this->t('This form demonstrates the Drupal 8 file api. Experiment with the form, and then look at the submit handlers in the code to understand the file api.'),
    ];

    $form['write_file'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Write to a file'),
    ];
    $form['write_file']['write_contents'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter something you would like to write to a file'),
      '#default_value' => $this->t('Put some text here or just use this text'),
    ];

    $form['write_file']['destination'] = [
      '#type' => 'textfield',
      '#default_value' => $default_file,
      '#title' => $this->t('Optional: Enter the streamwrapper saying where it should be written'),
      '#description' => $this->t('This may be public://some_dir/test_file.txt or private://another_dir/some_file.txt, for example. If you include a directory, it must already exist. The default is "public://". Since this example supports session://, you can also use something like session://somefile.txt.'),
    ];

    $form['write_file']['managed_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Write managed file'),
      '#submit' => ['::handleManagedFile'],
    ];
    $form['write_file']['unmanaged_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Write unmanaged file'),
      '#submit' => ['::handleUnmanagedFile'],
    ];
    $form['write_file']['unmanaged_php'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unmanaged using PHP'),
      '#submit' => ['::handleUnmanagedPhp'],
    ];

    $form['fileops'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Read from a file'),
    ];
    $form['fileops']['fileops_file'] = [
      '#type' => 'textfield',
      '#default_value' => $default_file,
      '#title' => $this->t('Enter the URI of a file'),
      '#description' => $this->t('This must be a stream-type description like public://some_file.txt or http://drupal.org or private://another_file.txt or (for this example) session://yet_another_file.txt.'),
    ];
    $form['fileops']['read_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Read the file and store it locally'),
      '#submit' => ['::handleFileRead'],
    ];
    $form['fileops']['delete_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete file'),
      '#submit' => ['::handleFileDelete'],
    ];
    $form['fileops']['check_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check to see if file exists'),
      '#submit' => ['::handleFileExists'],
    ];

    $form['directory'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create or prepare a directory'),
    ];

    $form['directory']['directory_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory to create/prepare/delete'),
      '#default_value' => $default_directory,
      '#description' => $this->t('This is a directory as in public://some/directory or private://another/dir.'),
    ];
    $form['directory']['create_directory'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create directory'),
      '#submit' => ['::handleDirectoryCreate'],
    ];
    $form['directory']['delete_directory'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete directory'),
      '#submit' => ['::handleDirectoryDelete'],
    ];
    $form['directory']['check_directory'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check to see if directory exists'),
      '#submit' => ['::handleDirectoryExists'],
    ];

    $form['debug'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Debugging'),
    ];
    $form['debug']['show_raw_session'] = [
      '#type' => 'submit',
      '#value' => $this->t('Show raw $_SESSION contents'),
      '#submit' => ['::handleShowSession'],
    ];
    $form['debug']['reset_session'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset the Session'),
      '#submit' => ['::handleResetSession'],
    ];

    return $form;
  }

  /**
   * Submit handler to write a managed file.
   *
   * A "managed file" is a file that Drupal tracks as a file entity.  It's the
   * standard way Drupal manages files in file fields and elsewhere.
   *
   * The key functions used here are:
   * - file_save_data(), which takes a buffer and saves it to a named file and
   *   also creates a tracking record in the database and returns a file object.
   *   In this function we use FILE_EXISTS_RENAME (the default) as the argument,
   *   which means that if there's an existing file, create a new non-colliding
   *   filename and use it.
   * - file_create_url(), which converts a URI in the form public://junk.txt or
   *   private://something/test.txt into a URL like
   *   http://example.com/sites/default/files/junk.txt.
   *    * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function handleManagedFile(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $data = $form_values['write_contents'];
    $uri = !empty($form_values['destination']) ? $form_values['destination'] : NULL;

    // Managed operations work with a file object.
    $file_object = \file_save_data($data, $uri, FILE_EXISTS_RENAME);
    if (!empty($file_object)) {
      $url = $this->getExternalUrl($file_object);
      $this->setDefaultFile($file_object->getFileUri());
      $file_data = $file_object->toArray();
      if ($url) {
        $this->messenger()->addMessage(
          $this->t('Saved managed file: %file to destination %destination (accessible via <a href=":url">this URL</a>, actual uri=<span id="uri">@uri</span>)', [
            '%file' => print_r($file_data, TRUE),
            '%destination' => $uri,
            '@uri' => $file_object->getFileUri(),
            ':url' => $url->toString(),
          ])
        );
      }
      else {
        // This Uri is not routable, so we cannot give a link to it.
        $this->messenger()->addMessage(
          $this->t('Saved managed file: %file to destination %destination (no URL, since this stream type does not support it)', [
            '%file' => print_r($file_data, TRUE),
            '%destination' => $uri,
            '@uri' => $file_object->getFileUri(),
          ])
        );
      }
    }
    else {
      $this->messenger()->addMessage($this->t('Failed to save the managed file'), 'error');
    }
  }

  /**
   * Submit handler to write an unmanaged file.
   *
   * An unmanaged file is a file that Drupal does not track.  A standard
   * operating system file, in other words.
   *
   * The key functions used here are:
   * - FileSystemInterface::saveData(), which takes a buffer and saves it to a
   *   named file, but does not create any kind of tracking record in the
   *   database. This example uses FILE_EXISTS_REPLACE for the third argument,
   *   meaning that if there's an existing file at this location, it should be
   *   replaced.
   * - file_create_url(), which converts a URI in the form public://junk.txt or
   *   private://something/test.txt into a URL like
   *   http://example.com/sites/default/files/junk.txt.
   *    * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function handleUnmanagedFile(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $data = $form_values['write_contents'];
    $destination = !empty($form_values['destination']) ? $form_values['destination'] : NULL;

    // With the unmanaged file we just get a filename back.
    $filename = $this->fileSystem->saveData($data, $destination, FILE_EXISTS_REPLACE);
    if ($filename) {
      $url = $this->getExternalUrl($filename);
      $this->setDefaultFile($filename);
      if ($url) {
        $this->messenger()->addMessage(
          $this->t('Saved file as %filename (accessible via <a href=":url">this URL</a>, uri=<span id="uri">@uri</span>)', [
            '%filename' => $filename,
            '@uri' => $filename,
            ':url' => $url->toString(),
          ])
        );
      }
      else {
        $this->messenger()->addMessage(
          $this->t('Saved file as %filename (not accessible externally)', [
            '%filename' => $filename,
            '@uri' => $filename,
          ])
        );
      }
    }
    else {
      $this->messenger()->addMessage($this->t('Failed to save the file'), 'error');
    }
  }

  /**
   * Submit handler to write an unmanaged file using plain PHP functions.
   *
   * The key functions used here are:
   * - FileSystemInterface::saveData(), which takes a buffer and saves it to a
   *   named file, but does not create any kind of tracking record in the
   *   database.
   * - file_create_url(), which converts a URI in the form public://junk.txt or
   *   private://something/test.txt into a URL like
   *   http://example.com/sites/default/files/junk.txt.
   * - drupal_tempnam() generates a temporary filename for use.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function handleUnmanagedPhp(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $data = $form_values['write_contents'];
    $destination = !empty($form_values['destination']) ? $form_values['destination'] : NULL;

    if (empty($destination)) {
      // If no destination has been provided, use a generated name.
      $destination = $this->fileSystem->tempnam('public://', 'file');
    }

    // With all traditional PHP functions we can use the stream wrapper notation
    // for a file as well.
    $fp = fopen($destination, 'w');

    // To demonstrate the fact that everything is based on streams, we'll do
    // multiple 5-character writes to put this to the file. We could easily
    // (and far more conveniently) write it in a single statement with
    // fwrite($fp, $data).
    $length = strlen($data);
    $write_size = 5;
    for ($i = 0; $i < $length; $i += $write_size) {
      $result = fwrite($fp, substr($data, $i, $write_size));
      if ($result === FALSE) {
        $this->messenger()->addMessage($this->t('Failed writing to the file %file', ['%file' => $destination]), 'error');
        fclose($fp);
        return;
      }
    }
    $url = $this->getExternalUrl($destination);
    $this->setDefaultFile($destination);
    if ($url) {
      $this->messenger()->addMessage(
        $this->t('Saved file as %filename (accessible via <a href=":url">this URL</a>, uri=<span id="uri">@uri</span>)', [
          '%filename' => $destination,
          '@uri' => $destination,
          ':url' => $url->toString(),
        ])
      );
    }
    else {
      $this->messenger()->addMessage(
        $this->t('Saved file as %filename (not accessible externally)', [
          '%filename' => $destination,
          '@uri' => $destination,
        ])
      );
    }
  }

  /**
   * Submit handler for reading a stream wrapper.
   *
   * Drupal now has full support for PHP's stream wrappers, which means that
   * instead of the traditional use of all the file functions
   * ($fp = fopen("/tmp/some_file.txt");) far more sophisticated and generalized
   * (and extensible) things can be opened as if they were files. Drupal itself
   * provides the public:// and private:// schemes for handling public and
   * private files. PHP provides file:// (the default) and http://, so that a
   * URL can be read or written (as in a POST) as if it were a file. In
   * addition, new schemes can be provided for custom applications. The Stream
   * Wrapper Example, if installed, impleents a custom 'session' scheme that
   * you can test with this example.
   *
   * Here we take the stream wrapper provided in the form. We grab the
   * contents with file_get_contents(). Notice that's it's as simple as that:
   * file_get_contents("http://example.com") or
   * file_get_contents("public://somefile.txt") just works. Although it's
   * not necessary, we use FileSystemInterface::saveData() to save this file
   * locally and then find a local URL for it by using file_create_url().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function handleFileRead(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $uri = $form_values['fileops_file'];

    if (empty($uri) or !is_file($uri)) {
      $this->messenger()->addMessage($this->t('The file "%uri" does not exist', ['%uri' => $uri]), 'error');
      return;
    }

    // Make a working filename to save this by stripping off the (possible)
    // file portion of the streamwrapper. If it's an evil file extension,
    // file_munge_filename() will neuter it.
    $filename = file_munge_filename(preg_replace('@^.*/@', '', $uri), '', TRUE);
    $buffer = file_get_contents($uri);

    if ($buffer) {
      $sourcename = $this->fileSystem->saveData($buffer, 'public://' . $filename);
      if ($sourcename) {
        $url = $this->getExternalUrl($sourcename);
        $this->setDefaultFile($sourcename);
        if ($url) {
          $this->messenger()->addMessage(
            $this->t('The file was read and copied to %filename which is accessible at <a href=":url">this URL</a>', [
              '%filename' => $sourcename,
              ':url' => $url->toString(),
            ])
          );
        }
        else {
          $this->messenger()->addMessage(
            $this->t('The file was read and copied to %filename (not accessible externally)', [
              '%filename' => $sourcename,
            ])
          );
        }
      }
      else {
        $this->messenger()->addMessage($this->t('Failed to save the file'));
      }
    }
    else {
      // We failed to get the contents of the requested file.
      $this->messenger()->addMessage($this->t('Failed to retrieve the file %file', ['%file' => $uri]));
    }
  }

  /**
   * Submit handler to delete a file.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function handleFileDelete(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $uri = $form_values['fileops_file'];

    // Since we don't know if the file is managed or not, look in the database
    // to see. Normally, code would be working with either managed or unmanaged
    // files, so this is not a typical situation.
    $file_object = $this->getManagedFile($uri);

    // If a managed file, use file_delete().
    if (!empty($file_object)) {
      // While file_delete should return FALSE on failure,
      // it can currently throw an exception on certain cache states.
      try {
        // This no longer returns a result code.  If things go bad,
        // it will throw an exception:
        $storage = $this->entityTypeManager->getStorage('file');
        $storage->delete([$file_object]);
        $this->messenger()->addMessage($this->t('Successfully deleted managed file %uri', ['%uri' => $uri]));
        $this->setDefaultFile($uri);
      }
      catch (\Exception $e) {
        $this->messenger()->addMessage($this->t('Failed deleting managed file %uri. Result was %result', [
          '%uri' => $uri,
          '%result' => print_r($e->getMessage(), TRUE),
        ]), 'error');
      }
    }
    // Else use FileSystemInterface::delete().
    else {
      $result = $this->fileSystem->delete($uri);
      if ($result !== TRUE) {
        $this->messenger()->addMessage($this->t('Failed deleting unmanaged file %uri', ['%uri' => $uri, 'error']));
      }
      else {
        $this->messenger()->addMessage($this->t('Successfully deleted unmanaged file %uri', ['%uri' => $uri]));
        $this->setDefaultFile($uri);
      }
    }
  }

  /**
   * Submit handler to check existence of a file.
   */
  public function handleFileExists(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $uri = $form_values['fileops_file'];
    if (is_file($uri)) {
      $this->messenger()->addMessage($this->t('The file %uri exists.', ['%uri' => $uri]));
    }
    else {
      $this->messenger()->addMessage($this->t('The file %uri does not exist.', ['%uri' => $uri]));
    }
  }

  /**
   * Submit handler for directory creation.
   *
   * Here we create a directory and set proper permissions on it using
   * FileSystemInterface::prepareDirectory().
   */
  public function handleDirectoryCreate(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $directory = $form_values['directory_name'];

    // The options passed to FileSystemInterface::prepareDirectory() are a
    // bitmask, so we can specify either FILE_MODIFY_PERMISSIONS (set
    // permissions on the directory), FILE_CREATE_DIRECTORY, or both together:
    // FILE_MODIFY_PERMISSIONS | FILE_CREATE_DIRECTORY.
    // FILE_MODIFY_PERMISSIONS will set the permissions of the directory by
    // by default to 0755, or to the value of the variable
    // 'file_chmod_directory'.
    if (!$this->fileSystem->prepareDirectory($directory, FILE_MODIFY_PERMISSIONS | FILE_CREATE_DIRECTORY)) {
      $this->messenger()->addMessage($this->t('Failed to create %directory.', ['%directory' => $directory]), 'error');
    }
    else {
      $result = is_dir($directory);
      $this->messenger()->addMessage($this->t('Directory %directory is ready for use.', ['%directory' => $directory]));
      $this->setDefaultDirectory($directory);
    }
  }

  /**
   * Submit handler for directory deletion.
   *
   * @see Drupal\Core\File\FileSystemInterface::deleteRecursive()
   */
  public function handleDirectoryDelete(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $directory = $form_values['directory_name'];

    $result = $this->fileSystem->deleteRecursive($directory);
    if (!$result) {
      $this->messenger()->addMessage($this->t('Failed to delete %directory.', ['%directory' => $directory]), 'error');
    }
    else {
      $this->messenger()->addMessage($this->t('Recursively deleted directory %directory.', ['%directory' => $directory]));
      $this->setDefaultDirectory($directory);
    }
  }

  /**
   * Submit handler to test directory existence.
   *
   * This actually just checks to see if the directory is writable.
   *
   * @param array $form
   *   FormAPI form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormAPI form state.
   */
  public function handleDirectoryExists(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $directory = $form_values['directory_name'];
    $result = is_dir($directory);
    if (!$result) {
      $this->messenger()->addMessage($this->t('Directory %directory does not exist.', ['%directory' => $directory]));
    }
    else {
      $this->messenger()->addMessage($this->t('Directory %directory exists.', ['%directory' => $directory]));
    }
  }

  /**
   * Utility submit function to show the contents of $_SESSION.
   */
  public function handleShowSession(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    // If the devel module is installed, use it's nicer message format.
    if ($this->moduleHandler->moduleExists('devel')) {
      // @codingStandardsIgnoreStart
      // We wrap this in the coding standards ignore tags because the use of
      // function dsm() is discouraged.
      dsm($this->getStoredData(), $this->t('Entire $_SESSION["file_example"]'));
      // @codingStandardsIgnoreEnd
    }
    else {
      $this->messenger()->addMessage(print_r($this->getStoredData(), TRUE));
    }
  }

  /**
   * Utility submit function to reset the demo.
   *
   * @param array $form
   *   FormAPI form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormAPI form state.
   *
   * @todo Note this does NOT clear any managed file references in Drupal's DB.
   *   It might be a good idea to add this.
   *   https://www.drupal.org/project/examples/issues/2985471
   */
  public function handleResetSession(array &$form, FormStateInterface $form_state) {
    $this->state->delete('file_example_default_file');
    $this->state->delete('file_example_default_directory');
    $this->clearStoredData();
    $this->messenger()->addMessage('Session reset.');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // We don't use this, but the interface requires us to implement it.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We don't use this, but the interface requires us to implement it.
  }

  /**
   * Get our stored data for display.
   */
  protected function getStoredData() {
    return $this->sessionHelper->getPath('');
  }

  /**
   * Reset our stored data.
   */
  protected function clearStoredData() {
    return $this->sessionHelper->cleanUpStore();
  }

}
