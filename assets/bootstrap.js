import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();
import { startStimulusApp } from '@symfony/stimulus-bundle';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);


