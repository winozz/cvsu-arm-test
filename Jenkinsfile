pipeline {
    agent any

    stages {
        stage('Run Jenkins Local Dev Deploy') {
            steps {
                withCredentials([
                    string(credentialsId: 'github-token', variable: 'GITHUB_TOKEN'),
                    string(credentialsId: 'laravel-app-key', variable: 'APP_KEY')
                ]) {
                    bat 'call "%WORKSPACE%\\jenkins-local-deploy.bat"'
                }
            }
        }
    }

    post {
        always {
            echo 'Build finished.'
        }
    }
}
